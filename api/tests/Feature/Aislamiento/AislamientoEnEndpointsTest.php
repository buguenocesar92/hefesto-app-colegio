<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Comunicacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRUEBAS CRÍTICAS: un identificador de otro colegio responde 404, no 403.
 *
 * POR QUÉ 404 Y NO 403
 * --------------------
 * Un 403 ("no tiene permiso") confirma que el recurso EXISTE en algún lado.
 * Para quien está probando identificadores, eso es una forma de enumerar el
 * contenido de otros colegios. La RLS hace que el recurso no sea visible, así
 * que 404 es además la respuesta correcta.
 *
 * Lo que se comprueba:
 *
 *  1. Un `id` de comunicación de otro colegio devuelve 404.
 *  2. Un `id` de alumno de otro colegio devuelve 404.
 *  3. El listado de un colegio no incluye filas del otro.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class AislamientoEnEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_comunicacion_de_otro_colegio_responde_404(): void
    {
        $primero = $this->crearColegio('endpoint-uno.cl');
        $ajena = Comunicacion::factory()->create([
            'tenant_id' => $primero['colegio']->id,
            'remitente_usuario_id' => $primero['comunicaciones']->id,
        ]);

        $segundo = $this->crearColegio('endpoint-dos.cl');

        $this->comoUsuario($segundo['direccion']);

        $respuesta = $this->getJson('/api/v1/comunicaciones/' . $ajena->id);

        $respuesta->assertNotFound();
    }

    public function test_un_alumno_de_otro_colegio_responde_404(): void
    {
        $primero = $this->crearColegio('endpoint-tres.cl');
        $ajeno = $this->crearAlumnoConFamilia($primero['colegio'], $primero['curso'])['alumno'];

        $segundo = $this->crearColegio('endpoint-cuatro.cl');

        $this->comoUsuario($segundo['direccion']);

        $this->getJson('/api/v1/alumnos/' . $ajeno->id)->assertNotFound();
    }

    public function test_el_listado_no_incluye_alumnos_de_otro_colegio(): void
    {
        $primero = $this->crearColegio('endpoint-cinco.cl');
        $deOtro = $this->crearAlumnoConFamilia($primero['colegio'], $primero['curso'], 'DeOtro')['alumno'];

        $segundo = $this->crearColegio('endpoint-seis.cl');
        $propio = $this->crearAlumnoConFamilia($segundo['colegio'], $segundo['curso'], 'Propio')['alumno'];

        $this->comoUsuario($segundo['direccion']);

        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertContains(
            $propio->id,
            $identificadores,
            'El alumno del colegio propio debe aparecer en su propio listado.'
        );

        $this->assertNotContains(
            $deOtro->id,
            $identificadores,
            'Un alumno de otro colegio no puede aparecer en el listado, ni siquiera sin abrir su ficha.'
        );
    }

    public function test_sin_sesion_no_se_ve_nada(): void
    {
        $colegio = $this->crearColegio('endpoint-siete.cl');
        Comunicacion::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'remitente_usuario_id' => $colegio['comunicaciones']->id,
        ]);

        // Sin `actingAs`: no hay sesión ni contexto de colegio.
        $this->getJson('/api/v1/comunicaciones')->assertUnauthorized();
        $this->getJson('/api/v1/alumnos')->assertUnauthorized();
    }

    public function test_la_escritura_en_otro_colegio_no_pasa_la_rls(): void
    {
        $primero = $this->crearColegio('endpoint-ocho.cl');
        $segundo = $this->crearColegio('endpoint-nueve.cl');

        $this->comoUsuario($segundo['comunicaciones']);

        // Se manda el `tenant_id` del primer colegio. La validación ignora ese
        // campo (no está en las reglas), pero la RLS tiene que rechazarlo
        // igual por WITH CHECK. Esta es la defensa que no depende del código
        // de la aplicación.
        $respuesta = $this->postJson('/api/v1/comunicaciones', [
            'tenant_id' => $primero['colegio']->id,
            'titulo' => 'Intento de escribir en otro colegio',
            'cuerpo' => 'Este texto debería ser rechazado por la RLS.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'curso_id' => $primero['curso']->id,
        ]);

        $this->assertContains(
            $respuesta->status(),
            [403, 404, 422, 500],
            'La escritura en nombre de otro colegio debe fallar por alguna razón.'
        );
    }
}