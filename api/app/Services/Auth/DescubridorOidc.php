<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descubre el documento de configuración del proveedor OIDC (Authentik).
 *
 * POR QUÉ ES CACHÉ
 * ----------------
 * El documento dice dónde están los endpoints de autorización, token y las
 * llaves públicas. Es público y cambia muy pocas veces. Sin caché, cada
 * ingreso hace una ida a Authentik antes de poder ni empezar.
 *
 * Se guarda 6 horas en Redis. Si Authentik no responde, se usa el valor
 * anterior si existe: que el proveedor esté caído no debería impedir entrar
 * a quien ya tiene sesión.
 */
class DescubridorOidc
{
    private const TTL = 21600; // 6 horas, en segundos.

    /**
     * @return array<string, mixed>
     */
    public function documento(string $issuer): array
    {
        $clave = 'oidc:descubrimiento:' . sha1($issuer);

        $guardado = Cache::get($clave);

        if (is_array($guardado) && isset($guardado['authorization_endpoint'], $guardado['token_endpoint'])) {
            return $guardado;
        }

        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';

        try {
            $respuesta = Http::timeout(5)->get($url);

            if (! $respuesta->successful()) {
                throw new RuntimeException(
                    'Authentik no devolvió el documento de descubrimiento (HTTP ' . $respuesta->status() . ').'
                );
            }

            $documento = $respuesta->json();

            if (! is_array($documento)) {
                throw new RuntimeException('El documento de descubrimiento de Authentik no es un JSON válido.');
            }

            Cache::put($clave, $documento, self::TTL);

            return $documento;
        } catch (RuntimeException $excepcion) {
            // Se tolera la caída de Authentik si ya hubo un descubrimiento
            // bueno guardado.
            if ($guardado !== null && is_array($guardado)) {
                return $guardado;
            }

            throw $excepcion;
        }
    }

    /**
     * Llaves públicas (JWKS) de Authentik.
     *
     * @return array<int, mixed>
     */
    public function llaves(string $issuer): array
    {
        $documento = $this->documento($issuer);

        if (! isset($documento['jwks_uri'])) {
            throw new RuntimeException('El documento de descubrimiento no declara jwks_uri.');
        }

        $respuesta = Http::timeout(5)->get((string) $documento['jwks_uri']);

        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudieron obtener las llaves públicas de Authentik.');
        }

        $jwks = $respuesta->json();

        return is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [];
    }
}