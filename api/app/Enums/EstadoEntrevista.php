<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una entrevista o reunión con la familia.
 *
 * "cancelada" es un estado terminal y se conserva, no se borra: la familia
 * necesita poder ver que hubo una entrevista que no se hizo.
 */
enum EstadoEntrevista: string
{
    case Agendada = 'agendada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Realizada => 'Realizada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** ¿Se puede cambiar la fecha u hora? */
    public function esEditable(): bool
    {
        return $this === self::Agendada;
    }
}