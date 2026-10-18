<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre apoderado y alumno.
 *
 * ESTA TABLA ES LA QUE PROTEGE AL APODERADO
 * ------------------------------------------
 * Un apoderado puede tener mas de un hijo y un alumno puede tener mas de un
 * apoderado (padre y madre, mas una abuela). Esta tabla es la unica fuente
 * de verdad de "quien puede ver a quien".
 *
 * `AlcanceDeAlumnos` la consulta para TODO listado de alumnos que devuelve
 * la API. No hay una segunda forma de obtener el alcance: si un endpoint
 * devuelve alumnos, pasa por ahi. Por eso un padre no puede ver la ficha de
 * el hijo de otro, ni siquiera filtrando el listado: las filas ajenas
 * simplemente no entran en la consulta.
 *
 * `es_responsable_legal` distingue a quien puede firmar documentos de quien
 * solo es contacto. Un tercero (cuidador, POR) recibe avisos pero no firma.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoderado_alumnos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('apoderado_id');
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->cascadeOnDelete();
            $tabla->uuid('alumno_id');
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();

            // "padre", "madre", "abuela", "abuelo", "tia", "tio", "otro".
            $tabla->string('vinculo', 40);

            $tabla->boolean('es_responsable_legal')->default(false);
            $tabla->boolean('es_contacto_emergencia')->default(false);

            $tabla->timestampsTz();

            // Evita duplicar el mismo vinculo dos veces.
            $tabla->unique(['tenant_id', 'apoderado_id', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderado_alumnos');
    }
};