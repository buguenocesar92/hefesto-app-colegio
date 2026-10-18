<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de comunicación.
 *
 * Sirven para que un aviso repetitivo (reunión de apoderados, cambio de
 * horario, campaña de matrícula) no se escriba desde cero cada vez, y sobre
 * todo para que el texto quede igual para todos. Un aviso entregado a 35
 * familias tiene que decir exactamente lo mismo a las 35.
 *
 * `requiere_acuse` se propone por defecto desde la plantilla, pero el aviso
 * concreto decide: hay plantillas cuyo texto no requiere confirmación y
 * avisos importantes que sí.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_comunicacion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            $tabla->string('nombre', 160);

            // "correo", "whatsapp", "presencial", "telefono", "web".
            $tabla->string('canal', 30)->default('correo');

            $tabla->string('asunto', 180);

            // El cuerpo puede llevar marcadores como {{nombre_apoderado}}.
            $tabla->text('cuerpo');

            $tabla->boolean('requiere_acuse')->default(false);
            $tabla->boolean('activa')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_comunicacion');
    }
};