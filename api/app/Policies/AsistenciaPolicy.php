<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asistencia;
use App\Models\Usuario;

/**
 * Quién puede registrar y ver asistencia.
 *
 * DIFERENCIA CON CONDUCTA
 * ----------------------
 * La asistencia es menos sensible que la conducta y por eso tiene más
 * lectores: el apoderado SÍ ve la asistencia de su hijo, porque la hoja de
 * asistencia es el documento que la familia pide todos los meses.
 *
 * El registro (quién la escribió) sigue siendo del colegio.
 *
 * Y una diferencia operativa que importa: solo el docente marca presencia.
 * Dirección y comunicaciones no: el que registra es el que estaba en la
 * sala.
 */
class AsistenciaPolicy
{
    public function __construct(private readonly \App\Services\Alumnos\AlcanceDeAlumnos $alcance) {}

    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->esDelColegio();
    }

    public function ver(Usuario $usuario, Asistencia $asistencia): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        // El apoderado ve la asistencia de su hijo: es un documento que le
        // corresponde.
        if ($usuario->esApoderado()) {
            return $this->alcance->esSuHijo($usuario, $asistencia->alumno);
        }

        if ($usuario->veTodosLosAlumnos()) {
            return true;
        }

        return $usuario->esDocente()
            && $this->alcance->estaEnCursoDelProfesional($usuario, $asistencia->alumno);
    }

    /**
     * ¿Puede registrar o corregir asistencia?
     *
     * Solo el docente a cargo. Corregir es tan sensible como registrar:
     * si cualquiera pudiera corregir, el registro dejaría de ser confiable.
     */
    public function registrar(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->esDocente();
    }

    /** ¿Puede ver la hoja de asistencia de un curso? */
    public function verCurso(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->esDocente();
    }
}