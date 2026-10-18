<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactos de emergencia.
 *
 * Son datos de terceros: NO son apoderados. Un contacto de emergencia puede
 * ser un vecino o un familiar que no tiene ninguna relacion administrativa
 * con el colegio, y por eso vive en su propia tabla y no en `apoderados`.
 *
 * Mezclarlos sería un error con consecuencias: el colegio terminaría
 * enviando comunicaciones legales a alguien que no tiene por qué recibirlas,
 * o otorgándole permisos a un tercero.
 *
 * `prioridad` ordena a quien se llama primero. El indice unico por alumno y
 * prioridad obliga a que las posiciones sean coherentes.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos_emergencia', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();

            $tabla->string('nombre', 120);
            $tabla->string('parentesco', 60);
            $tabla->string('telefono', 40);

            // 1 es el primero al que se llama.
            $tabla->unsignedSmallInteger('prioridad');

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'alumno_id', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos_emergencia');
    }
};