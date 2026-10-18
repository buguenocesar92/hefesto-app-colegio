<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profesionales del colegio: docentes, jefaturas de UTP, orientacion, psicologia.
 *
 * Se separa de `usuarios` a proposito. Un profesional existe en el colegio
 * aunque no tenga cuenta para entrar al sistema (puede ser un docente
 * reemplazado, o un profesional que solo aparece en actas). Y una cuenta de
 * acceso no siempre es un profesional: la secretaria de admisiones entra al
 * sistema y no tiene ficha profesional.
 *
 * `es_docente` decide si la persona puede registrar asistencia. Un
 * psicologo, por ejemplo, puede ver conducta pero no marcar presencia en un
 * curso que no es suyo.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->string('nombre', 80);
            $tabla->string('apellido', 80);
            $tabla->string('rut', 20)->nullable();
            $tabla->string('correo', 180)->nullable();
            $tabla->string('telefono', 40)->nullable();

            // "Jefe de UTP", "Orientadora", "Docente de Básica", "Psicóloga".
            $tabla->string('cargo', 120);

            $tabla->boolean('es_docente')->default(false);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'correo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};