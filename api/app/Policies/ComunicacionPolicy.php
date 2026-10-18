<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comunicacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Quién puede ver y enviar comunicaciones.
 *
 * EL MÓDULO CENTRAL TIENE SU PROPIA POLÍTICA
 * ------------------------------------------
 * No se resolvíó con una regla genérica de "tiene acceso al recurso", porque
 * aquí hay una asimetría que hay que decir de frente:
 *
 *  - El APODERADO no puede nunca redactar ni enviar una comunicación. Es el
 *    que las recibe. Si pudiera enviar, podría mandar un aviso en nombre
 *    del colegio a otros apoderados.
 *  - El APODERADO solo ve las comunicaciones dirigidas a su familia.
 *  - Los roles del colegio ven todas las del colegio, EXCEPTO el maestro de
 *    comunicaciones, que es justamente quien las redacta y por lo tanto debe
 *    verlas todas.
 *
 * `verAcuses` es una capacidad separada de `ver`: ver que se mandó un aviso
 * no es lo mismo que ver quién lo confirmó. Lo segundo es más delicado
 * porque es evidencia de la relación con una familia concreta.
 */
class ComunicacionPolicy
{
    /** ¿Puede ver el listado de comunicaciones? */
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->esDelColegio();
    }

    /** ¿Puede redactar y enviar? */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->enviaComunicaciones();
    }

    /**
     * ¿Puede ver el detalle de esta comunicación?
     *
     * Un apoderado solo ve las dirigidas a su familia. La comprobación se
     * hace contra `comunicacion_destinatarios`, no recalculando el curso:
     * lo que vale es a quién se le envió de verdad, que es lo que quedó
     * registrado.
     */
    public function ver(Usuario $usuario, Comunicacion $comunicacion): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->esDelColegio()) {
            return true;
        }

        if ($usuario->esApoderado() && $usuario->apoderado_id !== null) {
            return DB::table('comunicacion_destinatarios')
                ->where('comunicacion_id', $comunicacion->id)
                ->where('apoderado_id', $usuario->apoderado_id)
                ->exists();
        }

        return false;
    }

    /**
     * ¿Puede ver QUIÉN confirmó lectura?
     *
     * La RLS ya lo limita al colegio. Acá se limita al rol: es información
     * de contacto sobre una familia concreta.
     */
    public function verAcuses(Usuario $usuario, Comunicacion $comunicacion): bool
    {
        return $usuario->activo && $usuario->esDelColegio();
    }

    /**
     * ¿Puede registrar el acuse de una familia?
     *
     * Dos caminos legítimos:
     *  - la familia confirma desde el portal (`esApoderado`);
     *  - alguien del colegio anota que lo confirmó por otro canal, que es
     *    lo que pasa con WhatsApp, teléfono o presencial.
     */
    public function registrarAcuse(Usuario $usuario, Comunicacion $comunicacion): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->esDelColegio()) {
            return $this->ver($usuario, $comunicacion);
        }

        return $this->ver($usuario, $comunicacion);
    }
}