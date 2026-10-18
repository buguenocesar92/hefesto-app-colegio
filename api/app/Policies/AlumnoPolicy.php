<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Alumno;
use App\Models\Usuario;
use App\Services\Alumnos\AlcanceDeAlumnos;

/**
 * Quién puede ver qué alumno.
 *
 * DOS CAPAS, Y NO ES REDUNDANTE
 * -----------------------------
 * La RLS ya garantiza que el alumno y el usuario son del mismo colegio. Esta
 * política decide lo que la RLS no puede: que un padre no ve los hijos de
 * otro, y que un maestro de comunicaciones no ve fichas.
 *
 * Con una sola de las dos, el sistema tiene un agujero:
 *  - Solo RLS: un padre vería todos los alumnos de su colegio.
 *  - Solo la política: un padre podría abrir por URL la ficha de un alumno
 *    de otro colegio.
 *
 * EL RECURSO YA VIENE FILTRADO
 * ---------------------------
 * `show()` recibe un alumno que ya pasó por
 * `AlcanceDeAlumnos::apoderablesPor()`. Esta política es la segunda
 * comprobación, no la primera: si alguien saltara el filtro del listado,
 * el `ver()` de abajo seguiría rechazando.
 */
class AlumnoPolicy
{
    public function __construct(private readonly AlcanceDeAlumnos $alcance) {}

    /** ¿Puede ver el listado de alumnos? */
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * ¿Puede ver a ESTE alumno?
     *
     * Condición mínima: que esté en el alcance de la persona. No implica
     * ver la ficha completa (eso es `verFichaCompleta`).
     */
    public function ver(Usuario $usuario, Alumno $alumno): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->veTodosLosAlumnos()) {
            return true;
        }

        if ($usuario->esApoderado()) {
            return $this->alcance->esSuHijo($usuario, $alumno);
        }

        // Para el resto, la pregunta es si está en sus cursos. Se resuelve
        // con la misma consulta que usa el listado, así que no hay dos
        // criterios distintos que puedan desalinearse.
        return $this->alcance->apoderablesPor($usuario)->whereKey($alumno->id)->exists();
    }

    /**
     * ¿Puede ver la ficha COMPLETA (incluido lo sensible)?
     *
     * Distinto de `ver()`. El maestro de comunicaciones puede tener que
     * direccionar un aviso a una familia sin ver la ficha del alumno: para
     * eso le basta con saber a quién se le avisa.
     */
    public function verFichaCompleta(Usuario $usuario, Alumno $alumno): bool
    {
        return $this->alcance->puedeVerFichaCompleta($usuario, $alumno);
    }

    /** ¿Puede leer los incidentes de conducta? */
    public function verConducta(Usuario $usuario, Alumno $alumno): bool
    {
        return $this->alcance->puedeVerConducta($usuario, $alumno);
    }

    /** ¿Puede registrar la matrícula? */
    public function matricular(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** ¿Puede editar la ficha? */
    public function actualizar(Usuario $usuario, Alumno $alumno): bool
    {
        return $usuario->activo && $usuario->administra();
    }
}