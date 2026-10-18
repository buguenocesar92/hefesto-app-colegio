<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de la matrícula.
 *
 * Es distinto del estado del alumno: un alumno puede estar activo en el
 * colegio y no estar matriculado este año (estaba de cambio de nivel, fue
 * retirado del sistema y volvió). Por eso viven en tablas distintas.
 */
enum EstadoMatricula: string
{
    case Pendiente = 'pendiente';
    case Matriculado = 'matriculado';
    case Retirado = 'retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Matriculado => 'Matriculado',
            self::Retirado => 'Retirado',
        };
    }

    /** ¿Está en el colegio este año? */
    public function activa(): bool
    {
        return $this === self::Matriculado;
    }
}