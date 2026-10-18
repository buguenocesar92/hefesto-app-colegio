<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrevistas y reuniones con familias, y el acta de cada una.
 *
 * Van en la misma migración porque el acta no tiene sentido sin la
 * entrevista: es su registro de lo que se acordó.
 *
 * POR QUE EL ACTA ESTA DENTRO Y NO DENTRO DE LA ENTREVISTA
 * -------------------------------------------------------
 * Un acta puede existir sin entrevista (una reunión de curso que se
 * documenta para las familias pero no tiene una entrevista individual
 * asociada). Por eso `entrevista_id` admite nulo. A cambio, el acta conserva su propio titulo y
 * fecha: una vez firmados, no dependen de que la entrevista se reagende.
 *
 * `actas_reunion` es el documento que el colegio puede mostrar cuando una
 * familia pregunta que se acordo.
 *
 * AISLAMIMIENTO: ambas tablas llevan `tenant_id` y reciben Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $tabla->uuid('apoderado_id')->nullable();
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->nullOnDelete();
            $tabla->uuid('profesional_id')->nullable();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->nullOnDelete();

            // "entrevista_familiar", "reunion", "orientacion".
            $tabla->string('tipo', 30);

            $tabla->date('fecha');
            $tabla->time('hora');

            $tabla->string('lugar', 160)->nullable();

            // "agendada", "realizada", "cancelada".
            $tabla->string('estado', 20)->default('agendada');

            $tabla->text('motivo');
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'fecha']);
        });

        Schema::create('actas_reunion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->uuid('entrevista_id')->nullable();
            $tabla->foreign('entrevista_id')->references('id')->on('entrevistas')->nullOnDelete();

            $tabla->uuid('autor_usuario_id');
            $tabla->foreign('autor_usuario_id')->references('id')->on('usuarios')->restrictOnDelete();

            $tabla->string('titulo', 200);
            $tabla->text('resumen');
            $tabla->text('acuerdos')->nullable();

            $tabla->date('fecha');
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas_reunion');
        Schema::dropIfExists('entrevistas');
    }
};