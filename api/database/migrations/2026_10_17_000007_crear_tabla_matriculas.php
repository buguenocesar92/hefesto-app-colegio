<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriculas.
 *
 * Va aparte de `alumnos` porque un alumno puede tener varias matriculas a lo
 * largo de los años, y porque el estado de la matricula (pendiente,
 * matriculado, retirado) es una decisión de la secretaria, no parte de la
 * identidad del alumno.
 *
 * `numero_matricula` es la referencia que dicta la recepción al teléfono. Por
 * eso es única dentro del colegio y del año.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $tabla->uuid('curso_id');
            $tabla->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();

            $tabla->unsignedSmallInteger('anio_escolar');

            // Referencia visible, por ejemplo "2026-0042".
            $tabla->string('numero_matricula', 30);

            // "pendiente", "matriculado", "retirado".
            $tabla->string('estado', 20)->default('pendiente');

            $tabla->date('fecha_matricula')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestampsTz();

            // La referencia se repite solo dentro del mismo colegio y año.
            $tabla->unique(['tenant_id', 'anio_escolar', 'numero_matricula']);

            // Un alumno no se matricula dos veces en el mismo año.
            $tabla->unique(['tenant_id', 'alumno_id', 'anio_escolar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};