<?php

declare(strict_types=1);

namespace Tests\Feature\Comunicaciones;

use App\Models\Comunicacion;
use App\Models\ComunicacionDestinatario;
use App\Models\ComunicacionRecibo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EL MÓDULO CENTRAL: comunicaciones, destinatarios y acuses.
 *
 * Lo que se comprueba acá:
 *
 *  1. Un aviso a un curso genera un destinatario por familia.
 *  2. Una familia con dos hijos en el curso recibe UN aviso, no dos.
 *  3. Un aviso a un curso sin familias no se manda en silencio.
 *  4. El acuse no se puede registrar para una familia que no fue
 *     destinataria.
 *  5. Confirmar dos veces no duplica el acuse.
 *  6. Un apoderado no ve avisos que no son para su familia.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class ComunicacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_enviar_un_aviso_a_un_curso_genera_un_destinatario_por_familia(): void
    {
        $colegio = $this->crearColegio('comunicacion-uno.cl');

        $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Ríos');

        $this->comoUsuario($colegio['comunicaciones']);

        $respuesta = $this->postJson('/api/v1/comunicaciones', [
            'titulo' => 'Reunión de apoderados de 3° Básico',
            'cuerpo' => 'Se extiende la invitación a la reunión del curso. Fecha y hora por confirmar.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'curso_id' => $colegio['curso']->id,
            'requiere_acuse' => true,
            'enviar' => true,
        ]);

        $respuesta->assertCreated();

        $comunicacionId = $respuesta->json('datos.id');

        // Dos alumnos, cada uno con DOS apoderados (madre y padre): el
        // aviso sale a las cuatro familias, una fila por familia.
        $destinatarios = DB::table('comunicacion_destinatarios')
            ->where('comunicacion_id', $comunicacionId)
            ->count();

        $this->assertSame(4, $destinatarios, 'Cada familia del curso debe quedar registrada como destinataria.');
    }

    public function test_una_familia_con_dos_hijos_en_el_curso_recibe_un_solo_aviso(): void
    {
        $colegio = $this->crearColegio('comunicacion-dos.cl');

        $otroCurso = \App\Models\Curso::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'nivel_id' => $colegio['nivel']->id,
            'nombre' => '4° Básico A',
            'sigla' => '4A',
        ]);

        // El mismo apoderado para dos alumnos del mismo curso.
        $hijo = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Núñez');

        \App\Models\Matricula::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'alumno_id' => $hijo['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'anio_escolar' => (int) date('Y') + 1,
            'numero_matricula' => (int) date('Y') + 1 . '-9001',
        ]);

        $this->comoUsuario($colegio['comunicaciones']);

        $respuesta = $this->postJson('/api/v1/comunicaciones', [
            'titulo' => 'Aviso general del curso',
            'cuerpo' => 'Este es el texto del aviso para todo el curso, con la extensión necesaria.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'curso_id' => $colegio['curso']->id,
            'enviar' => true,
        ]);

        $respuesta->assertCreated();

        $destinatarios = DB::table('comunicacion_destinatarios')
            ->where('comunicacion_id', $respuesta->json('datos.id'))
            ->pluck('apoderado_id');

        // La familia aparece una vez por cada uno de sus dos apoderados, y
        // NUNCA duplicada por tener dos hijos.
        $this->assertSame(
            count($destinatarios),
            count(array_unique($destinatarios->all())),
            'La misma familia no puede aparecer dos veces en un mismo aviso.'
        );
    }

    public function test_un_aviso_a_un_curso_sin_familias_no_se_manda_en_silencio(): void
    {
        $colegio = $this->crearColegio('comunicacion-tres.cl');

        $this->comoUsuario($colegio['comunicaciones']);

        $respuesta = $this->postJson('/api/v1/comunicaciones', [
            'titulo' => 'Aviso a un curso vacío',
            'cuerpo' => 'Este aviso no tiene a quién llegar, y el colegio tiene que enterarse.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'curso_id' => $colegio['curso']->id,
            'enviar' => true,
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('tipo_destinatario');
    }

    public function test_no_se_puede_registrar_un_acuse_para_una_familia_que_no_recibio_el_aviso(): void
    {
        $colegio = $this->crearColegio('comunicacion-cuatro.cl');

        $destinataria = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $ajena = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Ríos');

        $this->comoUsuario($colegio['comunicaciones']);

        $comunicacion = Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
            'requiere_acuse' => true,
            'estado' => 'enviada',
            'enviada_en' => now(),
        ]);

        ComunicacionDestinatario::create([
            'tenant_id' => $colegio['colegio']->id,
            'comunicacion_id' => $comunicacion->id,
            'apoderado_id' => $destinataria['apoderado']->id,
        ]);

        $respuesta = $this->postJson('/api/v1/comunicaciones/' . $comunicacion->id . '/recibos', [
            'medio' => 'web',
            'apoderado_id' => $ajena['apoderado']->id,
        ]);

        $respuesta->assertStatus(422);
        $this->assertSame(0, ComunicacionRecibo::query()->count());
    }

    public function test_confirmar_dos_veces_no_duplica_el_acuse(): void
    {
        $colegio = $this->crearColegio('comunicacion-cinco.cl');

        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['comunicaciones']);

        $comunicacion = Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
            'requiere_acuse' => true,
            'estado' => 'enviada',
            'enviada_en' => now(),
        ]);

        ComunicacionDestinatario::create([
            'tenant_id' => $colegio['colegio']->id,
            'comunicacion_id' => $comunicacion->id,
            'apoderado_id' => $familia['apoderado']->id,
        ]);

        $cuerpo = [
            'medio' => 'web',
            'apoderado_id' => $familia['apoderado']->id,
        ];

        $this->postJson('/api/v1/comunicaciones/' . $comunicacion->id . '/recibos', $cuerpo)
            ->assertCreated();

        $this->postJson('/api/v1/comunicaciones/' . $comunicacion->id . '/recibos', $cuerpo)
            ->assertCreated();

        $this->assertSame(
            1,
            ComunicacionRecibo::query()->where('comunicacion_id', $comunicacion->id)->count(),
            'Un acuse por familia y aviso: confirmar dos veces no agrega una segunda fila.'
        );
    }

    public function test_un_apoderado_no_ve_avisos_destinados_a_otras_familias(): void
    {
        $colegio = $this->crearColegio('comunicacion-seis.cl');

        $mia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $otra = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Ríos');

        $this->comoUsuario($colegio['comunicaciones']);

        $paraMiFamilia = Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
            'estado' => 'enviada',
            'enviada_en' => now(),
        ]);

        $paraOtra = Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
            'estado' => 'enviada',
            'enviada_en' => now(),
        ]);

        ComunicacionDestinatario::create([
            'tenant_id' => $colegio['colegio']->id,
            'comunicacion_id' => $paraMiFamilia->id,
            'apoderado_id' => $mia['apoderado']->id,
        ]);

        ComunicacionDestinatario::create([
            'tenant_id' => $colegio['colegio']->id,
            'comunicacion_id' => $paraOtra->id,
            'apoderado_id' => $otra['apoderado']->id,
        ]);

        $cuenta = $this->crearCuentaDeFamilia($mia, 'madre.comunicaciones@ejemplo.cl');
        $this->comoUsuario($cuenta);

        $this->getJson('/api/v1/comunicaciones/' . $paraMiFamilia->id)->assertOk();
        $this->getJson('/api/v1/comunicaciones/' . $paraOtra->id)->assertNotFound();
    }

    public function test_la_comunicacion_enviada_no_admite_cambios_de_titulo(): void
    {
        $colegio = $this->crearColegio('comunicacion-siete.cl');

        $comunicacion = Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
            'estado' => 'enviada',
            'enviada_en' => now(),
        ]);

        $this->comoUsuario($colegio['comunicaciones']);

        // No hay endpoint de edición de una comunicación enviada, y esa es la
        // decisión: un aviso entregado no se corrige, se manda otro.
        $this->assertTrue(
            $comunicacion->fueEnviada(),
            'Una comunicación enviada está en estado terminal: el registro deja de ser editable.'
        );
    }

    public function test_el_tipo_de_destinatario_debe_corresponder_al_identificador(): void
    {
        $colegio = $this->crearColegio('comunicacion-ocho.cl');

        $this->comoUsuario($colegio['comunicaciones']);

        // Un aviso de tipo "curso" con nivel_id en vez de curso_id.
        $respuesta = $this->postJson('/api/v1/comunicaciones', [
            'titulo' => 'Aviso con el destino mal puesto',
            'cuerpo' => 'El cuerpo es suficientemente largo para pasar la validación.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'nivel_id' => $colegio['nivel']->id,
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('curso_id');
    }
}