<?php

declare(strict_types=1);

namespace Tests\Feature\Apoderado;

use App\Models\Alumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRUEBAS CRÍTICAS: un apoderado no ve los datos de los hijos de otro.
 *
 * POR QUÉ ESTA SUITE ESTÁ SEPARADA DE LA DE AISLAMIENTO
 * ---------------------------------------------------
 * Son dos cosas distintas y confundirlas es el error más grave posible en
 * este proyecto:
 *
 *  - AISLAMIENTO: que el Colegio Los Álamos no vea al Colegio San Antonio.
 *    Lo impone la Row Level Security de PostgreSQL.
 *
 *  - ALCANCE: que la madre de Martina no vea a Sebastián, que va al mismo
 *    curso, en la misma lista, en el mismo colegio. NADA de lo anterior lo
 *    impide: para la base de datos son dos filas del mismo tenant.
 *
 * La segunda la implementa `App\Services\Alumnos\AlcanceDeAlumnos`, y por eso
 * necesita pruebas propias.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class ApoderadoNoVeOtrosAlumnosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_apoderado_solo_ve_a_sus_propios_hijos_en_el_listado(): void
    {
        $colegio = $this->crearColegio('alcance-uno.cl');

        $martina = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $sebastian = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Ríos');

        $cuenta = $this->crearCuentaDeFamilia($martina, 'madre.alvarez@ejemplo.cl');

        $this->comoUsuario($cuenta);

        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertContains(
            $martina['alumno']->id,
            $identificadores,
            'La madre debe ver a su propia hija.'
        );

        $this->assertNotContains(
            $sebastian['alumno']->id,
            $identificadores,
            'La madre no debe ver a un alumno de otra familia, aunque esté en el mismo curso.'
        );
    }

    public function test_un_apoderado_no_puede_abrir_la_ficha_del_hijo_de_otro(): void
    {
        $colegio = $this->crearColegio('alcance-dos.cl');

        $martina = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $sebastian = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Ríos');

        $cuenta = $this->crearCuentaDeFamilia($martina, 'madre.alvarez2@ejemplo.cl');

        $this->comoUsuario($cuenta);

        // Directo por URL: el caso en que alguien cambia el identificador a
        // mano. La respuesta tiene que ser 404, no 403.
        $this->getJson('/api/v1/alumnos/' . $sebastian['alumno']->id)
            ->assertNotFound();
    }

    public function test_un_apoderado_con_dos_hijos_ve_a_los_dos(): void
    {
        $colegio = $this->crearColegio('alcance-tres.cl');

        $primera = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');
        $segunda = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        // Ambas familias se enlazan a la misma ficha de apoderado, como
        // cuando una madre tiene dos hijos en el colegio.
        $apoderado = $primera['apoderado'];

        \App\Models\ApoderadoAlumno::create([
            'tenant_id' => $colegio['colegio']->id,
            'apoderado_id' => $apoderado->id,
            'alumno_id' => $segunda['alumno']->id,
            'vinculo' => 'madre',
            'es_responsable_legal' => true,
        ]);

        $cuenta = $this->crearCuentaDeFamilia($primera, 'madre.dos.hijos@ejemplo.cl');

        $this->comoUsuario($cuenta);

        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertContains($primera['alumno']->id, $identificadores);
        $this->assertContains($segunda['alumno']->id, $identificadores);
    }

    public function test_un_apoderado_desactivado_no_ve_a_nadie(): void
    {
        $colegio = $this->crearColegio('alcance-cuatro.cl');

        $martina = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Álvarez');

        $cuenta = $this->crearCuentaDeFamilia($martina, 'madre.inactiva@ejemplo.cl');
        $cuenta->forceFill(['activo' => false])->save();

        $this->comoUsuario($cuenta);

        $respuesta = $this->getJson('/api/v1/alumnos');

        // Si el usuario está desactivado, no debe entrar nada. La respuesta
        // puede ser 403 (por sesión inválida) o un listado vacío, según dónde
        // se corte; lo que NO puede es devolver la ficha del alumno.
        $this->assertTrue(
            $respuesta->status() !== 200
                || $respuesta->json('data') === [],
            'Una cuenta desactivada no puede obtener el listado de alumnos.'
        );
    }

    public function test_un_docente_ve_solo_los_alumnos_de_sus_cursos(): void
    {
        $colegio = $this->crearColegio('alcance-cinco.cl');

        $propio = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Propio');

        // Un curso aparte, con otro alumno, en el mismo colegio. El docente
        // de prueba NO tiene carga ahí.
        $otroCurso = \App\Models\Curso::factory()->create([
            'tenant_id' => $colegio['colegio']->id,
            'nivel_id' => $colegio['nivel']->id,
            'nombre' => '5° Básico A',
            'sigla' => '5A',
        ]);

        $ajeno = $this->crearAlumnoConFamilia($colegio['colegio'], $otroCurso, 'Ajeno');

        $this->comoUsuario($colegio['docente']);

        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertContains(
            $propio['alumno']->id,
            $identificadores,
            'El docente ve a los alumnos de su curso.'
        );

        $this->assertNotContains(
            $ajeno['alumno']->id,
            $identificadores,
            'El docente no ve a los alumnos de un curso en el que no tiene carga.'
        );
    }

    public function test_el_maestro_de_comunicaciones_no_tiene_alcance_de_alumnos(): void
    {
        $colegio = $this->crearColegio('alcance-seis.cl');

        $alumno = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Alvarez');

        $this->comoUsuario($colegio['comunicaciones']);

        // El maestro de comunicaciones necesita poder mandar avisos, no ver
        // fichas. Su alcance de alumnos es vacío a propósito.
        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertNotContains(
            $alumno['alumno']->id,
            $identificadores,
            'El maestro de comunicaciones no debe ver fichas de alumnos.'
        );
    }

    public function test_la_directora_ve_todos_los_alumnos_de_su_colegio(): void
    {
        $colegio = $this->crearColegio('alcance-siete.cl');

        $uno = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Uno');
        $dos = $this->crearAlumnoConFamilia($colegio['colegio'], $colegio['curso'], 'Dos');

        $this->comoUsuario($colegio['direccion']);

        $identificadores = array_column(
            $this->getJson('/api/v1/alumnos')->assertOk()->json('data'),
            'id'
        );

        $this->assertContains($uno['alumno']->id, $identificadores);
        $this->assertContains($dos['alumno']->id, $identificadores);
    }
}