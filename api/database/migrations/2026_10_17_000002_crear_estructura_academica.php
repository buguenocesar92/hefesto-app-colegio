<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura academica: niveles y cursos.
 *
 * Las dos van juntas porque un nivel sin cursos no existe como unidad de
 * trabajo en este sistema, y separarlas solo parte un inventario en dos
 * migraciones sin ganar nada.
 *
 * AISLAMIENTO: las dos tablas llevan `tenant_id` y reciben Row Level
 * Security en la migracion `2026_10_17_000017_activar_row_level_security.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->string('nombre', 120);
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'nombre']);
        });

        Schema::create('cursos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('nivel_id');
            $tabla->foreign('nivel_id')->references('id')->on('niveles')->cascadeOnDelete();

            // "3° Basico A", "5° Basico B". Es lo que el colegio llama al curso.
            $tabla->string('nombre', 120);

            // Sigla corta ("3B") para mostrar en tablas y planillas.
            $tabla->string('sigla', 20);

            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'sigla']);
        });

        // Qué profesionales tienen a cargo qué curso.
        //
        // De aquí sale el alcance de un docente: los alumnos a los que puede
        // ver son los matriculados en los cursos donde tiene carga. Sin
        // esta tabla, el alcance tendría que adivinarse por el cargo del
        // profesional, y un profe de Matemática vería los alumnos de todos
        // los cursos donde hay Matemática, que no es lo mismo que tener el
        // curso a cargo.
        //
        // Un profesional puede tener carga en varios cursos (un jefe de UTP
        // cubre varios), de ahí que sea una tabla aparte y no una columna.
        Schema::create('curso_profesional', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('curso_id');
            $tabla->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $tabla->uuid('profesional_id');
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->cascadeOnDelete();

            // "titular", "reemplazo", "practica".
            $tabla->string('tipo', 30)->default('titular');

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'curso_id', 'profesional_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_profesional');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('niveles');
    }
};