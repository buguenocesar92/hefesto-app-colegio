<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ApoderadoAlumno;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas del colegio.
 *
 * LO IMPORTANTE DE ESTA CLASE
 * --------------------------
 * `actingAs()` autentica a una persona, pero NO fija el contexto de colegio
 * en la base de datos, porque el middleware `ResolverTenant` corre dentro del
 * grupo `web` y las pruebas no ejecutan ese grupo.
 *
 * Por eso toda prueba que toque una tabla con RLS tiene que fijar el
 * contexto explícitamente. Si no, la RLS se queda sin contra qué filtrar y
 * las pruebas pasan sin comprobar nada.
 *
 * `comoUsuario()` hace las dos cosas: autentica y fija.
 */
abstract class TestCase extends BaseTestCase
{
    /** Colegio fijado para la prueba en curso. */
    protected ?string $colegioDePrueba = null;

    /**
     * Autentica a una persona y fija su contexto de colegio en la base.
     */
    protected function comoUsuario(Usuario $usuario): static
    {
        $this->actingAs($usuario);

        return $this->fijarColegio($usuario->tenant_id);
    }

    /**
     * Emula lo que hace `App\Http\Middleware\ResolverTenant`.
     *
     * Se usa `set_config(..., false)`, es decir con alcance de SESIÓN y no de
     * transacción. En la aplicación real es al revés (alcance de
     * transacción, para que el worker de colas no arrastre el colegio de
     * otro).
     *
     * En las pruebas cada caso abre su propia aplicación y su propia
     * conexión, así que el valor no sobrevive entre pruebas: por eso el
     * alcance de sesión es seguro acá y hace falta para que las aserciones
     * (que corren fuera de la transacción del middleware) sigan viendo el
     * mismo contexto.
     *
     * Esto NO se validó en ejecución. Es una decisión razonada que puede
     * necesitar ajuste cuando las pruebas corran de verdad. Ver docs/10.
     */
    protected function fijarColegio(?string $colegioId): static
    {
        if ($colegioId === null) {
            return $this;
        }

        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, $colegioId]);

        $this->colegioDePrueba = $colegioId;

        return $this;
    }

    /**
     * Crea un colegio completo con estructura académica y usuarios.
     *
     * @return array<string, mixed>
     */
    protected function crearColegio(string $slug = 'colegio-prueba.cl'): array
    {
        $colegio = \App\Models\Colegio::factory()->create([
            'slug' => $slug,
            'dominio_correo' => $slug,
        ]);

        $this->fijarColegio($colegio->id);

        $nivel = Nivel::factory()->create([
            'tenant_id' => $colegio->id,
            'nombre' => 'Básica',
        ]);

        $curso = Curso::factory()->create([
            'tenant_id' => $colegio->id,
            'nivel_id' => $nivel->id,
            'nombre' => '3° Básico A',
            'sigla' => '3A',
        ]);

        $profesional = Profesional::factory()->create([
            'tenant_id' => $colegio->id,
            'es_docente' => true,
        ]);

        // El profesional tiene carga en el curso: de eso sale su alcance.
        $curso->profesionales()->attach($profesional->id, ['tipo' => 'titular']);

        return [
            'colegio' => $colegio,
            'nivel' => $nivel,
            'curso' => $curso,
            'profesional' => $profesional,
            'direccion' => $this->crearUsuario($colegio, RolUsuario::Direccion),
            'secretaria' => $this->crearUsuario($colegio, RolUsuario::Administracion),
            'docente' => $this->crearUsuario($colegio, RolUsuario::Docente, $profesional),
            'comunicaciones' => $this->crearUsuario($colegio, RolUsuario::Comunicaciones),
            'orientacion' => $this->crearUsuario($colegio, RolUsuario::Orientacion, $profesional),
        ];
    }

    /**
     * Crea un usuario del colegio con el rol indicado.
     */
    protected function crearUsuario(
        \App\Models\Colegio $colegio,
        RolUsuario $rol,
        ?Profesional $profesional = null,
    ): Usuario {
        return Usuario::factory()->create([
            'tenant_id' => $colegio->id,
            'rol' => $rol,
            'profesional_id' => $profesional?->id,
            'correo' => $rol->value . '.' . $colegio->slug . '@ejemplo.cl',
        ]);
    }

    /**
     * Crea un alumno matriculado en un curso, con su familia.
     *
     * @return array<string, mixed>
     */
    protected function crearAlumnoConFamilia(
        \App\Models\Colegio $colegio,
        Curso $curso,
        string $apellido = 'Pérez',
    ): array {
        $alumno = Alumno::factory()->create([
            'tenant_id' => $colegio->id,
            'apellido' => $apellido,
        ]);

        $apoderado = Apoderado::factory()->create([
            'tenant_id' => $colegio->id,
            'apellido' => $apellido,
            'correo' => mb_strtolower($apellido) . '.' . substr((string) $colegio->id, 0, 6) . '@ejemplo.cl',
        ]);

        ApoderadoAlumno::create([
            'tenant_id' => $colegio->id,
            'apoderado_id' => $apoderado->id,
            'alumno_id' => $alumno->id,
            'vinculo' => 'madre',
            'es_responsable_legal' => true,
        ]);

        Matricula::factory()->create([
            'tenant_id' => $colegio->id,
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => \App\Enums\EstadoMatricula::Matriculado,
        ]);

        return [
            'alumno' => $alumno,
            'apoderado' => $apoderado,
        ];
    }

    /**
     * Crea una cuenta de familia (rol `apoderado`) vinculada a un alumno.
     *
     * @param  array<string, mixed>  $vinculo  Resultado de `crearAlumnoConFamilia`.
     */
    protected function crearCuentaDeFamilia(array $vinculo, string $correo = 'familia@ejemplo.cl'): Usuario
    {
        $usuario = Usuario::factory()->create([
            'tenant_id' => $vinculo['apoderado']->tenant_id,
            'rol' => RolUsuario::Apoderado,
            'apoderado_id' => $vinculo['apoderado']->id,
            'correo' => $correo,
        ]);

        return $usuario;
    }
}