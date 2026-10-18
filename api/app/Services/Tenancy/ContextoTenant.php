<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de tenant de la petición.
 *
 * La RLS de PostgreSQL (migración 2026_10_17_000018) lee el tenant desde la
 * variable de sesión `app.tenant_id`. Esta clase es la ÚNICA que la escribe.
 * Si alguien la fija por otro lado, el comportamiento deja de estar
 * garantizado.
 *
 * ALCANCE DE TRANSACCIÓN
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir
 * la transacción. Con un `SET` normal (ámbito de sesión) el valor sobreviviría
 * a la petición y, en el worker de colas, que reutiliza la misma conexión
 * para trabajos de colegios distintos, un trabajo podría leer datos de otro
 * colegio. Ese es exactamente el fallo que este diseño evita.
 *
 * Por eso `establecer()` EXIGE estar dentro de una transacción y lanza una
 * excepción si no lo está.
 *
 * PATRÓN REUTILIZADO
 * ------------------
 * Es el mismo patrón documentado en
 * `apps/portal-autoservicio/docs/03-MULTITENANCY.md`, sección 3. No se
 * inventó un cuarto enfoque.
 */
class ContextoTenant
{
    /** Variable de sesión de PostgreSQL que lee la política RLS. */
    public const VARIABLE = 'app.tenant_id';

    /**
     * Fija el tenant de la petición en la conexión actual.
     *
     * @throws RuntimeException si no hay una transacción abierta.
     */
    public function establecer(string $tenantId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de tenant fuera de una transacción. '
                . 'Use App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $tenantId]);
    }

    /**
     * Ejecuta una operación dentro de una transacción con el tenant fijo.
     * Al salir, PostgreSQL descarta el valor de la variable.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(string $tenantId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($tenantId, $operacion) {
            $this->establecer($tenantId);

            return $operacion();
        });
    }

    /**
     * Lee la variable de sesión sin pasar por `public.app_tenant_id()`, que
     * lanza excepción cuando no hay contexto.
     *
     * Sirve para diagnóstico. Si devuelve null, ninguna política RLS de la
     * base va a devolver filas.
     */
    public function tenantIdEnConexion(): ?string
    {
        $fila = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        $valor = $fila->valor ?? null;

        return $valor === null || $valor === '' ? null : $valor;
    }

    /** Identificador del colegio autenticado, si lo hay. */
    public function tenantIdSolicitado(): ?string
    {
        return auth()->user()?->tenant_id;
    }
}