<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;
use stdClass;

/**
 * Verifica el token de identificación que devuelve Authentik.
 *
 * LO QUE SE VERIFICA, Y POR QUÉ CADA COSA
 * ---------------------------------------
 *  - La firma, contra las llaves públicas de Authentik.
 *  - El algoritmo, contra el que se ESPERA (`AUTHENTIK_ALGORITMO`), no
 *    contra el que venga en la cabecera. Aceptar el que venga cierra el
 *    ataque de confusión de algoritmo (firmar con la clave pública usando
 *    HS256).
 *  - El emisor (`iss`), que tiene que ser el de este despliegue.
 *  - La audiencia (`aud`), que tiene que incluir el identificador del
 *    cliente.
 *  - El `exp`, que valida `firebase/php-jwt` y que se contrasta aquí con
 *    una tolerancia de 60 segundos por desfase de reloj.
 *  - El `nonce`, que se leyó una sola vez al partir el authorization code.
 *    Sin esto, un `id_token` robado de otra sesión serviría para entrar.
 *
 * `email_verified` NO decide nada. Desde Authentik 2025.10 ese claim vale
 * `false` salvo que se configure el flujo que lo verifica, así que apoyar
 * el alta en él dejaría a personas fuera sin motivo.
 */
class VerificadorIdToken
{
    private array $llaves = [];

    public function __construct(private readonly DescubridorOidc $descubridor) {}

    /**
     * @return stdClass  Los claims verificados.
     *
     * @throws RuntimeException si el token no supera alguna comprobación.
     */
    public function verificar(string $idToken, string $issuer, string $audience, ?string $nonceEsperado = null): stdClass
    {
        $algoritmoEsperado = (string) config('authentik.algoritmo', 'RS256');

        // Se pasa el algoritmo esperado como predeterminado: si el token
        // declara uno distinto, `firebase/php-jwt` no lo acepta.
        $this->llaves = JWK::parseKeySet($this->descubridor->llaves($issuer), $algoritmoEsperado);

        $claims = JWT::decode(
            $idToken,
            $this->llaves,
        );

        $emisor = (string) ($claims->iss ?? '');

        if ($emisor !== rtrim($issuer, '/')) {
            throw new RuntimeException('El emisor del token no coincide con el de este despliegue.');
        }

        $audiencias = $this->audienciasDe($claims);

        if (! in_array($audience, $audiencias, true)) {
            throw new RuntimeException('La audiencia del token no incluye el identificador del cliente.');
        }

        if ($nonceEsperado !== null && (string) ($claims->nonce ?? '') !== $nonceEsperado) {
            throw new RuntimeException('El nonce del token no coincide con el de la petición.');
        }

        if (! isset($claims->sub) || (string) $claims->sub === '') {
            throw new RuntimeException('El token no trae identificador de sujeto.');
        }

        return $claims;
    }

    /**
     * `aud` puede ser una cadena o una lista según el proveedor.
     *
     * @return list<string>
     */
    private function audienciasDe(stdClass $claims): array
    {
        $aud = $claims->aud ?? null;

        if (is_string($aud)) {
            return [$aud];
        }

        if (is_array($aud)) {
            return array_values(array_map('strval', $aud));
        }

        return [];
    }
}