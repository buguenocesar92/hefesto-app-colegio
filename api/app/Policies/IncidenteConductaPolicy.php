<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncidenteConducta;
use App\Models\Usuario;

/**
 * Quién puede leer y escribir incidentes de conducta.
 *
 * LA POLÍTICA MÁS RESTRICTIVA DEL SISTEMA
 * ---------------------------------------
 * Porque la conducta es el dato que más duele si se filtra, y el que la ley
 * obliga a tratar con más cuidado.
 *
 * Tres reglas que no se negocian en el código:
 *
 *  1. El apoderado NUNCA lee el registro de incidentes. Ni el suyo. El
 *     colegio comunica los hechos a la familia por el canal formal, y el
 *     registro interno (incidencia, gravedad, medida) no es el documento
 *     que se entrega. La familia ve la comunicación, no el parte.
 *  2. El maestro de comunicaciones tampoco. Redactar un aviso no requiere
 *     saber qué pasó detrás, y si lo supiera, el aviso dejaría de servir.
 *  3. Solo dirección, orientación y el docente del curso pueden leer.
 */
class IncidenteConductaPolicy
{
    public function __construct(private readonly \App\Services\Alumnos\AlcanceDeAlumnos $alcance) {}

    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->veConducta();
    }

    public function ver(Usuario $usuario, IncidenteConducta $incidente): bool
    {
        if (! $usuario->activo || ! $usuario->veConducta()) {
            return false;
        }

        return $this->alcance->puedeVerConducta($usuario, $incidente->alumno);
    }

    /** ¿Puede registrar un incidente? */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->registraConducta();
    }

    /**
     * ¿Puede modificar o eliminar un incidente?
     *
     * Solo quien lo registró y mientras no haya pasado nada. Un registro de
     * conducta que se puede borrar no sirve para nada: alguien tiene que
     * poder responder "sí, eso pasó, y el colegio lo registró".
     */
    public function modificar(Usuario $usuario, IncidenteConducta $incidente): bool
    {
        return $this->crear($usuario)
            && $incidente->registrado_por_usuario_id === $usuario->id;
    }

    /**
     * ¿Puede marcar que se notificó a la familia?
     *
     * Lo puede hacer cualquiera del colegio que tenga relación con el
     * alumno, porque el aviso lo puede mandar otro (comunicaciones).
     */
    public function notificar(Usuario $usuario, IncidenteConducta $incidente): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        return $this->alcance->puedeVerConducta($usuario, $incidente->alumno)
            || $usuario->enviaComunicaciones();
    }
}