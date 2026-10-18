<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Apoderado;
use App\Models\Colegio;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Decide a qué colegio pertenece una identidad de Authentik.
 *
 * LA REGLA, Y POR QUÉ NO ES MÁS SIMPLE
 * -------------------------------------
 * Un alta solo ocurre si se cumple una de estas dos:
 *
 *  1. El correo pertenece al dominio declarado por un colegio.
 *  2. La cuenta ya existía (creada por la secretaria al matricular).
 *
 * Y NO hay un tercer camino: no se crea una cuenta porque alguien se
 * inventó un correo con el dominio del colegio. El dominio es una
 * declaración del colegio, no una prueba de identidad: sirve para decidir a
 * qué colegio pertenece la cuenta al CREARLA, y para eso hace falta que
 * alguien del colegio haya declarado ese dominio.
 *
 * MIENTRAS TANTO
 * -------------
 * El permiso no lo da el grupo de Authentik. Un grupo dice "esta persona
 * entra al sistema"; el `rol` de la tabla `usuarios` dice "qué puede ver".
 * Si el grupo cambiara mañana, el acceso ya registrado no cambiaría solo.
 *
 * `email_verified` NO decide nada: desde Authentik 2025.10 vale `false` en
 * la mayoría de los flujos.
 */
class ResolvedorDeCuenta
{
    /**
     * Devuelve el usuario del colegio, o `null` si no puede entrar.
     *
     * @param  stdClass  $claims  Claims verificados del token de identificación.
     */
    public function resolver(stdClass $claims): ?Usuario
    {
        $sub = (string) $claims->sub;
        $correo = strtolower(trim((string) ($claims->email ?? '')));

        // 1. La cuenta ya existe. Es el camino normal.
        $usuario = Usuario::query()->where('authentik_sub', $sub)->first();

        if ($usuario !== null) {
            if (! $usuario->activo) {
                Log::warning('Intento de acceso con cuenta desactivada.', [
                    'sub' => $sub,
                    'colegio' => $usuario->tenant_id,
                ]);

                return null;
            }

            return $usuario;
        }

        // 2. La cuenta no existe: se da de alta solo si el correo cae en el
        //    dominio declarado por un colegio.
        if ($correo === '' || ! str_contains($correo, '@')) {
            return null;
        }

        $dominio = substr($correo, (int) strpos($correo, '@') + 1);

        $colegio = Colegio::query()
            ->where('dominio_correo', $dominio)
            ->where('activo', true)
            ->first();

        if ($colegio === null) {
            Log::info('Alta rechazada: el dominio no pertenece a ningún colegio activo.', ['dominio' => $dominio]);

            return null;
        }

        // 3. El alta escribe en tablas con RLS: necesita contexto de tenant.
        return $this->darDeAltaEn($colegio->id, $sub, $correo, $claims);
    }

    /**
     * Crea el usuario dentro del contexto del colegio.
     */
    private function darDeAltaEn(string $colegioId, string $sub, string $correo, stdClass $claims): Usuario
    {
        $contexto = new ContextoTenant();

        // La columna `rol` es NOT NULL y tiene una revisión de valores. El
        // rol por defecto es `comunicaciones`: es el más acotado de los que
        // no son familia. Nadie entra como dirección por el solo hecho de
        // tener un correo del dominio; Dirección se asigna a mano.
        return $contexto->ejecutarAislado($colegioId, function () use ($colegioId, $sub, $correo, $claims): Usuario {
            $nombre = (string) ($claims->given_name ?? '');
            $apellido = (string) ($claims->family_name ?? '');

            if (trim($nombre . $apellido) === '') {
                $nombre = 'Cuenta';
                $apellido = 'por confirmar';
            }

            return Usuario::create([
                'tenant_id' => $colegioId,
                'authentik_sub' => $sub,
                'correo' => $correo,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'rol' => \App\Enums\RolUsuario::Comunicaciones,
                'activo' => true,
                'ultimo_acceso' => now(),
            ]);
        });
    }

    /**
     * Vincula una cuenta de familia con su ficha de apoderado.
     *
     * Sin este vínculo, un apoderado entra al sistema pero no ve a sus
     * hijos: `AlcanceDeAlumnos` necesita el `apoderado_id`.
     */
    public function vincularComoApoderado(Usuario $usuario, string $colegioId): bool
    {
        $contexto = new ContextoTenant();

        return $contexto->ejecutarAislado($colegioId, function () use ($usuario, $colegioId): bool {
            $apoderado = Apoderado::query()
                ->where('correo', $usuario->correo)
                ->first();

            if ($apoderado === null) {
                return false;
            }

            $usuario->forceFill([
                'apoderado_id' => $apoderado->id,
                'rol' => \App\Enums\RolUsuario::Apoderado,
            ])->save();

            return true;
        });
    }
}