<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A quién se dirige una comunicación.
 *
 * El destinatario se RESUELVE al enviar: un aviso a un curso de 35 alumnos
 * se convierte en 35 filas de destinatarios. Por eso los tipos son
 * descriptivos ("a un curso entero") y no contenedores.
 */
enum TipoDestinatario: string
{
    /** A todas las familias con un hijo en el curso. */
    case Curso = 'curso';

    /** A todas las familias con un hijo en el nivel. */
    case Nivel = 'nivel';

    /** A una familia concreta. */
    case Apoderado = 'apoderado';

    /** A una familia concreta, por un hecho de su hijo. */
    case Alumno = 'alumno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Curso => 'Curso',
            self::Nivel => 'Nivel',
            self::Apoderado => 'Apoderado',
            self::Alumno => 'Alumno',
        };
    }

    /** ¿El tipo se resuelve a partir del curso? */
    public function requiereCurso(): bool
    {
        return $this === self::Curso;
    }

    /** ¿El tipo se resuelve a partir del nivel? */
    public function requiereNivel(): bool
    {
        return $this === self::Nivel;
    }
}