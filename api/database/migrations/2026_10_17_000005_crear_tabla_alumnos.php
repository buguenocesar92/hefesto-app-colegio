<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumnos: la ficha.
 *
 * DATOS SENSIBLES
 * ---------------
 * Esta tabla concentra los datos mas delicados del sistema. El dato de un
 * menor es dato sensible bajo la Ley 19.628 (modificada por la Ley 20.720),
 * y el colegio tiene ademas obligaciones de la Ley 21.719 que deben
 * revisarse con la asesoria legal del colegio (ver docs/07).
 *
 * Lo que NO esta en esta tabla, a proposito:
 *
 *  - La asistencia vive en `asistencias`.
 *  - La conducta vive en `conducta_incidentes`.
 *  - La matricula vive en `matriculas`.
 *
 * No son decisiones de estilo. Si las tres estuvieran aqui, "ver ficha" y
 * "leer conducta" serian la misma operacion, y no se podrian dar permisos
 * distintos sobre ellas. Ver docs/02, seccion "Por que asistencia y conducta
 * estan separadas".
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->string('nombre', 80);
            $tabla->string('apellido', 80);

            // El RUT identifica a un menor en Chile. Es opcional porque hay
            // casos en que el colegio todavia no lo tiene, pero cuando existe
            // no puede repetirse dentro del mismo colegio.
            $tabla->string('rut', 20)->nullable();

            $tabla->date('fecha_nacimiento');

            // "femenino", "masculino", "otro", "no_informado".
            $tabla->string('sexo', 20)->default('no_informado');

            $tabla->string('direccion', 240)->nullable();
            $tabla->string('telefono', 40)->nullable();
            $tabla->string('correo', 180)->nullable();

            $tabla->date('fecha_ingreso')->nullable();

            // Alumno retirado: se conserva la ficha porque el historial de
            // comunicaciones y las actas de entrevista existen, pero deja de
            // aparecer en los listados de curso.
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};