<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de sesiones.
 *
 * La sesión vive en la base, no en memoria: con varias réplicas de la API
 * (ver deploy/docker-compose.prod.yml) una sesión iniciada en una réplica
 * tiene que seguir viva en la otra.
 *
 * No lleva `tenant_id` y no lleva RLS a propósito: la resuelve Laravel por
 * identificador de sesión, no la aplicación. Es la misma excepción que
 * `colegios`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();
            $tabla->uuid('user_id')->nullable()->index();
            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->text('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};