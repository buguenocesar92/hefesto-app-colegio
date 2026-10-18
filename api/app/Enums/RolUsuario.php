<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de la persona dentro del colegio.
 *
 * EL MODELO DE PERMISOS Y POR QUÉ
 * -------------------------------
 * Se eligió un rol POR PERSONA, no un permiso por recurso. La razón es que
 * en un colegio pequeño las personas tienen cargos, no capacidades
 * atomizadas: la secretaria que da de alta matrículas es "la secretaria",
 * no "alguien con permiso de escritura en matrículas". Un modelo
 * de permisos finos obligaría al colegio a inventar una taxonomía que no
 * entiende, y casi siempre terminaría equivalente a un rol.
 *
 * Lo que sí se separó es el dato SENSIBLE, y esa separación es el eje del
 * modelo:
 *
 *  - Un `apoderado` nunca ve la ficha de un alumno que no es su hijo.
 *  - Un `comunicaciones` (el maestro de comunicaciones, que redacta y
 *    envía los avisos) NO ve fichas completas ni registros de conducta. Ve
 *    los avisos que envía y a quién se los envía. Es el rol que la ley
 *    obliga a mantener acotado: redactar un aviso no requiere conocer el
 *    hecho que lo origina.
 *  - Un `orientacion` (psicología, orientadora) sí ve conducta, si el
 *    colegio decide otorgarlo.
 *
 * El último punto es una decisión DEL COLEGIO, no del software. Acá está en
 * el sentido más acotado; el colegio puede retirarlo (docs/03).
 *
 * La justificación completa, con lo que cada rol puede y no puede hacer,
 * está en docs/03, sección "Modelo de permisos".
 */
enum RolUsuario: string
{
    /** Dirección del colegio. Ve todo lo de su colegio. */
    case Direccion = 'direccion';

    /** Administración: matrículas, asistencia, catálogo. Sin conducta. */
    case Administracion = 'administracion';

    /** Docente: sus cursos, asistencia y ficha de sus alumnos. */
    case Docente = 'docente';

    /**
     * Maestro de comunicaciones. Redacta y envía avisos, agenda entrevistas.
     *
     * A propósito NO ve fichas completas ni conducta. Es el rol que existe
     * para que el colegio pueda mandar a alguien a hablar con las familias
     * sin darle acceso a todo lo que hay detrás.
     */
    case Comunicaciones = 'comunicaciones';

    /** Orientación o psicología: sí ve conducta de sus alumnos. */
    case Orientacion = 'orientacion';

    /** Familia. Solo sus propios hijos. */
    case Apoderado = 'apoderado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Direccion => 'Dirección',
            self::Administracion => 'Administración',
            self::Docente => 'Docente',
            self::Comunicaciones => 'Comunicaciones',
            self::Orientacion => 'Orientación',
            self::Apoderado => 'Apoderado',
        };
    }

    /**
     * ¿Administra el colegio (altas, catálogo, matrículas)?
     */
    public function administra(): bool
    {
        return in_array($this, [self::Direccion, self::Administracion], true);
    }

    /**
     * ¿Accede al sistema como persona del colegio (no como familia)?
     *
     * La diferencia importa en la interfaz: un apoderado no debe ver
     * menús de administración nilistados de todo el colegio.
     */
    public function esDelColegio(): bool
    {
        return $this !== self::Apoderado;
    }

    /**
     * ¿Puede registrar asistencia?
     *
     * Solo el docente. Ni dirección ni comunicaciones: el que marca
     * presencia es el que está en la sala.
     */
    public function registraAsistencia(): bool
    {
        return $this === self::Docente;
    }

    /**
     * ¿Puede registrar incidentes de conducta?
     *
     * El docente que está en la sala registra el hecho; orientación
     * registra el seguimiento.
     */
    public function registraConducta(): bool
    {
        return in_array($this, [self::Docente, self::Orientacion], true);
    }

    /**
     * ¿Puede LEER registros de conducta?
     *
     * El listado de roles sale de `config('colegio.roles_que_leen_conducta')`
     * y no del código. La comparación es por el VALOR del enum ('docente'),
     * porque el valor es lo que está en la base y en la variable de entorno.
     *
     * Es configurable por un caso real: en un colegio chico la dirección y la
     * orientadora suelen ser la misma persona, y si el rol quedara fijo,
     * podría registrar un incidente y no volver a leerlo.
     *
     * Ver docs/03, sección "Modelo de permisos".
     */
    public function leeConducta(): bool
    {
        // La lista sale de la configuración y no del código. Ver docs/03: hay
        // colegios donde dirección y orientación son la misma persona, y esa
        // persona tiene que poder leer lo que registró.
        $roles = (array) config('colegio.roles_que_leen_conducta', ['direccion', 'orientacion', 'docente']);

        return in_array($this->value, $roles, true);
    }

    /**
     * ¿Puede enviar comunicaciones?
     *
     * Todos los roles del colegio menos administración: quien administra no
     * redacta avisos. El apoderado NO envía comunicaciones a otros: es el
     * que las recibe.
     */
    public function enviaComunicaciones(): bool
    {
        return in_array($this, [
            self::Direccion,
            self::Docente,
            self::Comunicaciones,
            self::Orientacion,
        ], true);
    }

    /**
     * ¿Puede gestionar la agenda de entrevistas?
     */
    public function gestionaAgenda(): bool
    {
        return $this->esDelColegio();
    }
}