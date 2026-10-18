<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

/**
 * Una sola política para los catálogos de administración.
 *
 * POR QUÉ UNA SOLA PARA VARIAS TABLAS
 * ----------------------------------
 * Niveles, cursos, profesionales, plantillas de comunicación, usuarios y
 * consentimientos se administran exactamente igual: los crea, edita y desactiva
 * dirección o administración. Escribir seis clases idénticas no agrega
 * seguridad, solo seis lugares donde olvidarse de algo.
 *
 * Se registran juntas en `ColegioServiceProvider` con `Gate::policy()`.
 *
 * LO QUE SÍ TIENE REGLA PROPIA
 * ----------------------------
 * `consentimientos` no es un catálogo común: registrar el consentimiento de
 * una familia es un acto con consecuencia legal (docs/07). El método
 * `registrarConsentimiento()` está aparte, y también se lo puede negar a un
 * colegio que quiera separar la firma de la administración.
 */
class GestionAdministrativaPolicy
{
    /** ¿Puede ver el catálogo? */
    public function ver(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** ¿Puede crear entradas? */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** ¿Puede editar entradas? */
    public function actualizar(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** ¿Puede desactivar una entrada? */
    public function desactivar(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** ¿Puede gestionar las cuentas de acceso? */
    public function gestionarUsuarios(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->rol === \App\Enums\RolUsuario::Direccion;
    }

    /**
     * ¿Puede registrar el consentimiento de una familia?
     *
     * También la secretaria: el consentimiento se firma en recepción, que es
     * exactamente la persona que tiene el documento firmado en la mano.
     */
    public function registrarConsentimiento(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }
}