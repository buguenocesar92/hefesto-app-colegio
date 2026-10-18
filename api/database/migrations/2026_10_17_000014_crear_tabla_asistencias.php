<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ASISTENCIA. Tabla propia, y a proposito.
 *
 * POR QUE NO ESTA JUNTO A LA CONDUCTA
 * -----------------------------------
 * Porque no son lo mismo. El colegio tiene que poder preguntar "¿el alumno
 * estuvo presente?" y "¿el alumno tuvo un incidente?", y son preguntas
 * distintas, con plazos distintos y con consecuencias distintas.
 *
 * Mezclarlas en una sola tabla obligaria a guardar, en cada fila diaria, una
 * columna `estado` que a veces significa "falto" y a veces "se portó mal".
 * El resultado sería un historial que no se puede consultar y permisos que no
 * se pueden diferenciar:
 *
 *  - La hoja de asistencia necesita contar presencias de un mes.
 *  - La bitácora de convivencia necesita contar incidentes y medidas.
 *  - Un docente que registra asistencia NO debería poder registrar conducta
 *    a un alumno que no es de su curso, y un maestro de comunicaciones
 *    no debería ver ninguna de las dos.
 *
 * Separarlas permite que cada consulta diga exactamente que lee. Ver
 * docs/02.
 *
 * LA REGLA DE ORO
 * ---------------
 * Un `null` en `estado` NO significa ausente: significa "aun no registrado".
 * Es la diferencia entre "el alumno faltó" y "el colegio todavia no lo sabe",
 * y son dos hechos que no se pueden inventar uno por el otro.
 *
 * `justificacion` vive aca porque justifica una inasistencia, no una
 * conducta. La medida aplicada ante un comportamiento va en
 * `conducta_incidentes.medida`.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $tabla->uuid('curso_id');
            $tabla->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();

            $tabla->date('fecha');

            // "presente", "ausente", "tarde", "justificado".
            $tabla->string('estado', 20);

            $tabla->text('justificacion')->nullable();

            $tabla->uuid('registrado_por_usuario_id');
            $tabla->foreign('registrado_por_usuario_id')->references('id')->on('usuarios')->restrictOnDelete();

            $tabla->timestampTz('registrado_en');

            $tabla->timestampsTz();

            // Una fila por alumno por día. Sin esta restriccion, el mismo
            // día puede quedar registrado dos veces con estados distintos y
            // el conteo mensual deja de ser confiable.
            $tabla->unique(['tenant_id', 'alumno_id', 'fecha']);

            // El indice que usa la hoja de asistencia del curso.
            $tabla->index(['tenant_id', 'curso_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};