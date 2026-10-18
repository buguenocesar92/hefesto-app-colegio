<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de la asistencia de un alumno en un día.
 *
 * ATENCIÓN AL `Justificado`
 * -------------------------
 * "justificado" es una AUSENCIA con explicación, no una presencia. Si se
 * contara como presente, el porcentaje de asistencia de un alumno con
 * justificativos médicos subiría sin que haya venido nunca. Las consultas
 * de inasistencia lo tratan distinto de "presente" a propósito.
 *
 * Un valor nulo NO es ninguno de estos: significa "aún no registrado".
 */
enum EstadoAsistencia: string
{
    case Presente = 'presente';
    case Ausente = 'ausente';
    case Tarde = 'tarde';
    case Justificado = 'justificado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Presente => 'Presente',
            self::Ausente => 'Ausente',
            self::Tarde => 'Llegó tarde',
            self::Justificado => 'Ausencia justificada',
        };
    }

    /** ¿Cuento como día presente para el porcentaje? */
    public function cuentaComoPresente(): bool
    {
        return $this === self::Presente || $this === self::Tarde;
    }

    /** ¿Es una ausencia, con o sin justificación? */
    public function esAusencia(): bool
    {
        return $this === self::Ausente || $this === self::Justificado;
    }

    /** ¿Exige un motivo escrito? */
    public function exigeJustificacion(): bool
    {
        return $this === self::Justificado;
    }
}