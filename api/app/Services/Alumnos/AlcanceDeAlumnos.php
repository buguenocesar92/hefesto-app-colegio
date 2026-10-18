<?php

declare(strict_types=1);

namespace App\Services\Alumnos;

use App\Models\Alumno;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * UN SOLO LUGAR donde se decide a qué alumnos puede ver una persona.
 *
 * POR QUÉ EXISTE ESTA CLASE
 * -------------------------
 * El aislamiento entre colegios lo impone la base de datos (RLS). Pero
 * dentro de un colegio hay un segundo aislamiento, que la base no puede
 * hacer: **un apoderado no puede ver los datos de los hijos de otro**.
 *
 * Ese segundo filtro depende del rol, y por eso es fácil de implementar mal:
 * basta con que un controlador se olvide de una línea y aparece la ficha de
 * un niño que no le corresponde a quien la está mirando. Con datos de
 * menores, eso no es un error de programación: es un incidente.
 *
 * La solución NO es "recordar aplicar el filtro", sino que no exista forma
 * de devolver alumnos sin pasar por acá. Todo endpoint que devuelva alumnos
 * llama a `apoderablesPor()` o a `todos()`, y ninguno arma la consulta por
 * su cuenta.
 *
 * LAS TRES PREGUNTAS QUE RESPONDE
 * ------------------------------
 *  1. ¿Puede ver el listado completo? (roles del colegio)
 *  2. ¿Cuáles son los alumnos que le corresponden? (apoderado)
 *  3. ¿Puede ver la ficha COMPLETA, con lo sensible? (más restrictivo que
 *     ver que el alumno existe)
 *
 * LA TERCERA ES LA IMPORTANTE
 * --------------------------
 * Ver que existe un alumno y ver su ficha completa son dos permisos
 * distintos. Un maestro de comunicaciones puede tener que direccionar un
 * aviso a una familia sin necesitar ver la ficha del alumno. Por eso
 * `puedeVerFichaCompleta()` existe separada de `ver()`.
 *
 * AISLAMIENTO EN DOS CAPAS
 * ------------------------
 * Este servicio decide DENTRO del colegio. Que sea el colegio correcto lo
 * garantiza la RLS. Las dos capas hacen falta: RLS sin este filtro dejaría
 * que un padre viera todo su colegio; este filtro sin RLS dejaría que un
 * padre abriera una ficha de otro colegio por URL.
 */
class AlcanceDeAlumnos
{
    /**
     * Consulta de alumnos acotada a lo que la persona puede ver.
     *
     * TODOS los listados de alumnos pasan por acá. No hay excepción.
     */
    public function apoderablesPor(Usuario $usuario): Builder
    {
        $consulta = Alumno::query()->with(['matricula']);

        // Un apoderado solo ve a sus propios hijos, y solo si su cuenta
        // está activa.
        if ($usuario->esApoderado()) {
            return $consulta
                ->whereIn('id', function ($interna) use ($usuario): void {
                    $interna->select('alumno_id')
                        ->from('apoderado_alumnos')
                        ->where('apoderado_id', $usuario->apoderado_id);
                })
                ->where('activo', true);
        }

        if (! $usuario->activo) {
            // Una cuenta desactivada no ve nada, aunque la fila siga viva.
            return $consulta->whereRaw('1 = 0');
        }

        if ($usuario->veTodosLosAlumnos()) {
            return $consulta;
        }

        // Un docente u orientador ve los alumnos de los cursos que tiene a
        // cargo, determinados por su ficha profesional.
        if ($usuario->profesional_id !== null) {
            return $consulta->whereIn('id', function ($interna) use ($usuario): void {
                $interna->select('m.alumno_id')
                    ->from('matriculas as m')
                    ->join('curso_profesional as cp', 'cp.curso_id', '=', 'm.curso_id')
                    ->where('cp.profesional_id', $usuario->profesional_id)
                    ->where('m.estado', 'matriculado');
            });
        }

        // Cualquier otro rol del colegio que no sea docente (por ejemplo
        // comunicaciones) no tiene alcance de alumnos por sí mismo: ve lo
        // que se le envía, no las fichas.
        return $consulta->whereRaw('1 = 0');
    }

    /**
     * ¿Puede ver esta ficha con todo su contenido?
     *
     * Es más restrictivo que ver que el alumno existe. Lo que decide:
     *
     *  - dirección y administración: todo.
     *  - apoderado: sus hijos, y solo los campos que la familia tiene
     *    derecho a ver (el Resource aplica el recorte).
     *  - docente y orientación: los alumnos de sus cursos.
     *  - comunicaciones: NO. Puede ver que existe un alumno para
     *    direccionar un aviso, pero no la ficha. Esta es la línea que el
     *    colegio puede mover si lo decide (docs/03).
     */
    public function puedeVerFichaCompleta(Usuario $usuario, Alumno $alumno): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->veTodosLosAlumnos()) {
            return true;
        }

        if ($usuario->esApoderado()) {
            return $this->esSuHijo($usuario, $alumno);
        }

        if ($usuario->esDocente() || $usuario->veConducta()) {
            return $this->estaEnSusCursos($usuario, $alumno);
        }

        return false;
    }

    /** ¿Puede leer los incidentes de conducta de este alumno? */
    public function puedeVerConducta(Usuario $usuario, Alumno $alumno): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->veTodosLosAlumnos()) {
            return true;
        }

        if ($usuario->veConducta()) {
            return $this->estaEnSusCursos($usuario, $alumno);
        }

        // El apoderado NO ve el registro de conducta. Es una decisión, no un
        // olvido: el colegio comunica los hechos a la familia por el canal
        // formal, y el registro interno de incidencia y medida no es lo que
        // se entrega. Si el colegio decide distinto, se cambia acá.
        return false;
    }

    /** ¿Puede registrar o modificar asistencia de este alumno? */
    public function puedeRegistrarAsistencia(Usuario $usuario, Alumno $alumno): bool
    {
        if (! $usuario->activo || ! $usuario->esDocente()) {
            return false;
        }

        return $this->estaEnSusCursos($usuario, $alumno);
    }

    /** ¿Es este alumno hijo de este apoderado? */
    public function esSuHijo(Usuario $usuario, Alumno $alumno): bool
    {
        if ($usuario->apoderado_id === null) {
            return false;
        }

        return \App\Models\ApoderadoAlumno::query()
            ->where('apoderado_id', $usuario->apoderado_id)
            ->where('alumno_id', $alumno->id)
            ->exists();
    }

    /**
     * ¿El alumno está en un curso a cargo de este profesional?
     *
     * Versión pública de la comprobación que usa `estaEnSusCursos()`, para
     * que las Policies no repitan la consulta.
     */
    public function estaEnCursoDelProfesional(Usuario $usuario, Alumno $alumno): bool
    {
        return $this->estaEnSusCursos($usuario, $alumno);
    }

    /** ¿El alumno está matriculado en algún curso a cargo de esta persona? */
    private function estaEnSusCursos(Usuario $usuario, Alumno $alumno): bool
    {
        if ($usuario->profesional_id === null) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('matriculas as m')
            ->join('curso_profesional as cp', 'cp.curso_id', '=', 'm.curso_id')
            ->where('cp.profesional_id', $usuario->profesional_id)
            ->where('m.alumno_id', $alumno->id)
            ->exists();
    }
}