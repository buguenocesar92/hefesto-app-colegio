<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de consentimiento para tratar datos personales.
 *
 * POR QUE ESTA TABLA EXISTE
 * -------------------------
 * Desde la Ley 20.720, el dato vinculado a menores de edad es DATO SENSIBLE
 * bajo la Ley 19.628. Para datos sensibles la ley exige consentimiento
 * explicito, y ese consentimiento hay que poder probar despues. Un colegio
 * que no puede mostrar cuando y con que documento una familia autorizo el
 * tratamiento esta en problemas el dia que la familia lo pregunta.
 *
 * Esta tabla es el respaldo. `evidencia_ruta` apunta al documento firmado
 * guardado fuera de la base (un escaneo). Si no hay documento, se deja en
 * blanco y el hecho queda registrado igual, que es preferible a no
 * registrar nada.
 *
 * OJO: esta tabla NO reemplaza el cumplimiento legal ni lo reemplaza el
 * colegio. Ver docs/07, seccion "Lo que este sistema no reemplaza".
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('apoderado_id');
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->cascadeOnDelete();

            // Puede ser para un alumno en concreto o para todos los hijos de
            // esa familia. Por eso `alumno_id` admite nulo.
            $tabla->uuid('alumno_id')->nullable();
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();

            // "matricula", "comunicaciones", "datos_sensibles", "todos".
            $tabla->string('alcance', 40);

            // Version del documento que se firmo. Sin esto no se puede
            // probar que el consentimiento cubria el texto vigente.
            $tabla->string('version_documento', 40);

            // Quien firmo, escrito a mano por si no coincide con el apoderado
            // titular (por ejemplo, un abuelo con permiso notarial).
            $tabla->string('otorgado_por', 160);
            $tabla->date('fecha_otorgamiento');

            $tabla->string('evidencia_ruta', 300)->nullable();

            // Revocar no borra la fila: deja constancia de que existed.
            $tabla->timestampTz('revocado_en')->nullable();

            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};