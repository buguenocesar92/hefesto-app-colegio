<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categoría del incidente de conducta.
 *
 * Deliberadamente es un catálogo corto y school-neutral. No se modelan
 * "peleas", "roces" ni "bullying" como categorías propias: lo que pasó se
 * describe en texto libre, y la categoría sirve para agrupar y contar.
 *
 * Meter categorías cada vez más específicas sería inventar el reglamento
 * interno del colegio dentro del software, y ese reglamento cambia cada
 * año.
 */
enum CategoriaIncidente: string
{
    case Convivencia = 'convivencia';
    case Academico = 'academico';
    case Seguridad = 'seguridad';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Convivencia => 'Convivencia',
            self::Academico => 'Académico',
            self::Seguridad => 'Seguridad',
            self::Otro => 'Otro',
        };
    }
}