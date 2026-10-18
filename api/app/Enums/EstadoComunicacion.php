<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una comunicación.
 *
 * Solo dos estados, a propósito. Un "pendiente de envío" con reintentos, un
 * "programado" y un "fallido" tienen sentido si el sistema envía de verdad.
 * Este sistema REGISTRA lo que el colegio ya envió, así que el único estado
 * intermedio que existe de verdad es "todavía no se manda": el borrador.
 *
 * Un borrador se puede editar y borrar. Una comunicación enviada no se
 * edita: se envía otra. Si se pudiera modificar el texto de un aviso ya
 * entregado, el registro dejaría de servir para demostrar qué se le dijo a
 * la familia, que es justamente su razón de existir.
 */
enum EstadoComunicacion: string
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada',
        };
    }

    /** ¿El contenido ya no se puede cambiar? */
    public function esFinal(): bool
    {
        return $this === self::Enviada;
    }
}