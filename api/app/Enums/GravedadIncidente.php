<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Gravedad de un incidente de conducta.
 *
 * Va aparte de la asistencia porque el criterio es otro. La asistencia se
 * mide contra el calendario; la conducta se mide contra el reglamento
 * interno del colegio. Por eso la gravedad no es "cuánto faltó", sino
 * "cuánto importa": un golpe y una pelea por lo mismo pueden tener
 * distinta consecuencia según el curso y la reiteración.
 *
 * `media` y `grave` son las que exigen comunicar a la familia. Está escrito
 * en el enum para que no dependa de que alguien se acuerde.
 */
enum GravedadIncidente: string
{
    case Leve = 'leve';
    case Media = 'media';
    case Grave = 'grave';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Leve => 'Leve',
            self::Media => 'Media',
            self::Grave => 'Grave',
        };
    }

    /** ¿Requiere aviso a la familia? */
    public function exigeComunicacion(): bool
    {
        return $this !== self::Leve;
    }

    /** ¿Requiere entrevista con la familia? */
    public function exigeEntrevista(): bool
    {
        return $this === self::Grave;
    }
}