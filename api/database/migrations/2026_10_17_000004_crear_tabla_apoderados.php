<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apoderados: las personas de la familia que se comunican con el colegio.
 *
 * DATOS SENSIBLES
 * ---------------
 * Un apoderado es una persona adulta, pero sus datos identifican a un menor.
 * Desde la Ley 20.720, el dato vinculado a menores de edad es dato sensible
 * (ver docs/07). Por eso esta tabla queda protegida por RLS igual que los
 * alumnos y su lectura queda en la bitacora de accesos.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoderados', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->string('nombre', 80);
            $tabla->string('apellido', 80);
            $tabla->string('rut', 20)->nullable();

            // Un correo identifica a una sola familia dentro del colegio.
            $tabla->string('correo', 180);

            $tabla->string('telefono', 40)->nullable();
            $tabla->string('direccion', 240)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'correo']);
            $tabla->unique(['tenant_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderados');
    }
};