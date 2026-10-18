<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores de referencia.
 *
 * Sirve para generar la referencia de cada comunicación ("COM-2026-000137")
 * sin que dos avisos del mismo colegio puedan quedar con el mismo número.
 *
 * El bloqueo de fila (`SELECT ... FOR UPDATE`) ocurre en
 * `App\Services\Comunicaciones\GeneradorDeReferencia`, dentro de la
 * transacción que ya abrió el middleware de tenant.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contadores_folio', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            // Que se está contando: "comunicacion".
            $tabla->string('clave', 60);
            $tabla->unsignedBigInteger('ultimo_numero')->default(0);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores_folio');
    }
};