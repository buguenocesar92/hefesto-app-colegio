<?php

declare(strict_types=1);

namespace Tests\Feature\Registro;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\IncidenteConducta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ASISTENCIA Y CONDUCTA: por qué son dos tablas y por qué son dos permisos.
 *
 * Lo que se comprueba acá:
 *
 *  1. Registrar asistencia NO crea un registro de conducta, ni al revés.
 *  2. Un apoderado ve la asistencia de su hijo.
 *  3. Un apoderado NO ve los incidentes de conducta, ni del hijo propio.
 *  4. El maestro de comunicaciones NO ve incidentes.
 *  5. El docente sí ve los incidentes de sus alumnos.
 *  6. Una ausencia justificada exige justificación.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class AsistenciaYConductaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_asistencia_no_crea_registros_de_conducta(): void
    {
        $colegio = $this->crearColegio('registro-uno.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        $this->postJson('/api/v1/asistencias', [
            'fecha' => now()->subDay()->toDateString(),
            'curso_id' => $colegio['curso']->id,
            'registros' => [
                [
                    'alumno_id' => $familia['alumno']->id,
                    'estado' => EstadoAsistencia::Ausente->value,
                    'justificacion' => 'Falta sin aviso.',
                ],
            ],
        ])->assertCreated();

        $this->assertSame(1, Asistencia::query()->count());
        $this->assertSame(
            0,
            IncidenteConducta::query()->count(),
            'Una inasistencia NO es un incidente de conducta. Son tablas distintas a propósito.'
        );
    }

    public function test_registrar_conducta_no_toca_la_asistencia(): void
    {
        $colegio = $this->crearColegio('registro-dos.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        $this->postJson('/api/v1/conducta', [
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'categoria' => 'convivencia',
            'gravedad' => 'leve',
            'descripcion' => 'Interrumpió la clase en dos ocasiones, se conversó en el momento.',
        ])->assertCreated();

        $this->assertSame(1, IncidenteConducta::query()->count());
        $this->assertSame(
            0,
            Asistencia::query()->count(),
            'Un incidente de conducta NO es un registro de asistencia.'
        );
    }

    public function test_un_apoderado_ve_la_asistencia_de_su_hijo(): void
    {
        $colegio = $this->crearColegio('registro-tres.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        Asistencia::factory()->create([
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'estado' => EstadoAsistencia::Presente,
            'registrado_por_usuario_id' => $colegio['docente']->id,
            'registrado_en' => now(),
        ]);

        $cuenta = $this->crearCuentaDeFamilia($familia, 'madre.asistencia@ejemplo.cl');
        $this->comoUsuario($cuenta);

        // La hoja de asistencia es un documento que la familia pide todos los
        // meses: tiene que poder verla.
        $this->getJson('/api/v1/asistencias?fecha=' . now()->subDay()->toDateString())
            ->assertOk();
    }

    public function test_un_apoderado_no_ve_los_incidentes_de_conducta_de_su_propio_hijo(): void
    {
        $colegio = $this->crearColegio('registro-cuatro.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        IncidenteConducta::factory()->create([
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'categoria' => 'convivencia',
            'gravedad' => 'media',
            'descripcion' => 'Participó en una discusión con un compañero durante el recreo.',
            'registrado_por_usuario_id' => $colegio['docente']->id,
            'registrado_en' => now(),
        ]);

        $cuenta = $this->crearCuentaDeFamilia($familia, 'madre.conducta@ejemplo.cl');
        $this->comoUsuario($cuenta);

        // El colegio le comunica los hechos a la familia por la vía formal
        // (una comunicación), no entregándole el parte interno.
        $this->getJson('/api/v1/conducta')->assertNotFound();
    }

    public function test_el_maestro_de_comunicaciones_no_ve_incidentes(): void
    {
        $colegio = $this->crearColegio('registro-cinco.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        IncidenteConducta::factory()->create([
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'categoria' => 'convivencia',
            'gravedad' => 'media',
            'descripcion' => 'Participó en una discusión con un compañero durante el recreo.',
            'registrado_por_usuario_id' => $colegio['docente']->id,
            'registrado_en' => now(),
        ]);

        // El maestro de comunicaciones redacta los avisos, pero redactar un
        // aviso no requiere saber qué pasó detrás.
        $this->comoUsuario($colegio['comunicaciones']);
        $this->getJson('/api/v1/conducta')->assertNotFound();
    }

    public function test_el_docente_ve_los_incidentes_de_sus_alumnos(): void
    {
        $colegio = $this->crearColegio('registro-seis.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        IncidenteConducta::factory()->create([
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'categoria' => 'convivencia',
            'gravedad' => 'leve',
            'descripcion' => 'Interrumpió la clase, se conversó en el momento.',
            'registrado_por_usuario_id' => $colegio['docente']->id,
            'registrado_en' => now(),
        ]);

        $respuesta = $this->getJson('/api/v1/conducta')->assertOk();

        $this->assertCount(1, $respuesta->json('data'));
    }

    public function test_una_ausencia_justificada_exige_justificacion(): void
    {
        $colegio = $this->crearColegio('registro-siete.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        $respuesta = $this->postJson('/api/v1/asistencias', [
            'fecha' => now()->subDay()->toDateString(),
            'curso_id' => $colegio['curso']->id,
            'registros' => [
                [
                    'alumno_id' => $familia['alumno']->id,
                    'estado' => EstadoAsistencia::Justificado->value,
                ],
            ],
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_la_descripcion_de_un_incidente_debe_ser_util(): void
    {
        $colegio = $this->crearColegio('registro-ocho.cl');
        $familia = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $this->comoUsuario($colegio['docente']);

        $respuesta = $this->postJson('/api/v1/conducta', [
            'alumno_id' => $familia['alumno']->id,
            'curso_id' => $colegio['curso']->id,
            'fecha' => now()->subDay()->toDateString(),
            'categoria' => 'convivencia',
            'gravedad' => 'leve',
            'descripcion' => 'se portó mal',
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('descripcion');
    }
}