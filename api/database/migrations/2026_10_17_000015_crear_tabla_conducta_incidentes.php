<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONDUCTA. Tabla propia, separada de asistencia.
 *
 * POR QUE ESTA SEPARADA
 * ---------------------
 * Un incidente de conducta es un hecho documentado, con autor del registro,
 * gravedad y medida aplicada. La asistencia es un dato diario de presencia.
 * Mezclarlas obligaría a que la mitad de las filas "{}" completara campos
 * que no le corresponden, y a que una consulta de conducta terminara leyendo
 * filas de presencia.
 *
 * LA SEPARACIÓN ES SOBRE TODO UNA DECISIÓN DE PERMISOS
 * ---------------------------------------------------
 * La conducta es el dato más sensible de este sistema, después del nombre.
 * Por eso vive aislada, con su propia Policy y con su propia regla de
 * alcance. Un maestro de comunicaciones puede ver el aviso que se le envió
 * a una familia sin poder leer por qué se le envió: el aviso se sostiene solo
 * como registro, sin exponer el hecho que lo origina.
 *
 * La excepción es un rol: `orientacion` (psicología, orientadora) sí
 * ve conducta de los alumnos de sus cursos, si el colegio decide otorgarlo.
 * Esa decisión es del colegio y se documenta en docs/03.
 *
 * `comunicacion_id` enlaza el incidente con el aviso que se le envió a la
 * familia. Es opcional porque no todo incidente se comunica el mismo día.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conducta_incidentes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $tabla->uuid('curso_id');
            $tabla->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();

            $tabla->date('fecha');

            // "convivencia", "academico", "seguridad", "otro".
            $tabla->string('categoria', 30);

            // "leve", "media", "grave".
            $tabla->string('gravedad', 20);

            $tabla->text('descripcion');

            // Qué se hizo. "Convocación del apoderado", "refuerzo positivo
            // en sala", etc.
            $tabla->text('medida')->nullable();

            $tabla->uuid('registrado_por_usuario_id');
            $tabla->foreign('registrado_por_usuario_id')->references('id')->on('usuarios')->restrictOnDelete();

            $tabla->timestampTz('registrado_en');

            // Si la familia ya fue avisada por comunicación, se deja la
            // referencia. No es obligatorio: la notificación puede hacerse
            // verbalmente y anotarse acá.
            $tabla->boolean('notificado_apoderado')->default(false);

            $tabla->uuid('comunicacion_id')->nullable();
            $tabla->foreign('comunicacion_id')->references('id')->on('comunicaciones')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'alumno_id', 'fecha']);
            $tabla->index(['tenant_id', 'curso_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conducta_incidentes');
    }
};