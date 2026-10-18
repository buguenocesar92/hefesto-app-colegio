<?php

declare(strict_types=1);

namespace App\Services\Comunicaciones;

use Illuminate\Support\Facades\DB;

/**
 * Genera la referencia de una comunicación, sin repetir.
 *
 * "COM-2026-000137".
 *
 * POR QUÉ NO ES UN `count() + 1`
 * ------------------------------
 * Dos avisos enviados en el mismo segundo llegarían a leer el mismo
 * contador. La fila se bloquea con `FOR UPDATE` dentro de la transacción
 * que ya abrió el middleware de tenant, así que el segundo espera.
 *
 * El bloqueo vive aquí y no en la aplicación, porque la garantía la da
 * PostgreSQL. Si mañana dos procesos escriben a la vez, el índice único de
 * `contadores_folio` sigue siendo la red de seguridad.
 *
 * La transacción es la del middleware (`ContextoTenant::ejecutarAislado`):
 * por eso el `FOR UPDATE` no se cuelga en una conexión del pool.
 */
class GeneradorDeReferencia
{
    private const PREFIJO = 'COM';

    /**
     * Siguiente referencia para un colegio.
     */
    public function siguiente(string $tenantId, string $clave = 'comunicacion'): string
    {
        $fila = DB::table('contadores_folio')
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->lockForUpdate()
            ->first();

        if ($fila === null) {
            DB::table('contadores_folio')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenantId,
                'clave' => $clave,
                'ultimo_numero' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $numero = 1;
        } else {
            $numero = (int) $fila->ultimo_numero + 1;

            DB::table('contadores_folio')
                ->where('id', $fila->id)
                ->update(['ultimo_numero' => $numero, 'updated_at' => now()]);
        }

        $anio = now()->year;

        return sprintf('%s-%d-%06d', self::PREFIJO, $anio, $numero);
    }
}