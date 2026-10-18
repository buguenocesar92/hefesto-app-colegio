<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL MODULO CENTRAL: comunicaciones, destinatarios y acuses de recibo.
 *
 * Este es el corazon del producto. El problema de negocio que resuelve es
 * concreto: cuando una familia pregunta "¿que le avisaron a mi hijo?", el
 * colegio tiene que poder responder "esto, el dia tal, por este canal, y
 * usted lo recibio el dia tal". Sin registro, la respuesta es "no se".
 *
 * LAS TRES TABLAS Y POR QUE
 * -------------------------
 * `comunicaciones` es lo que se dijo: que, cuando, por que canal, y quien
 * lo envio.
 *
 * `comunicacion_destinatarios` es A QUIEN se le dijo. Va aparte porque los
 * destinatarios se resuelven al ENVIAR (de un curso o de un nivel salen
 * varias familias) y porque el "¿le avisaron a esta familia?" es una
 * pregunta sobre el vinculo, no sobre el aviso.
 *
 * `comunicacion_recibos` es la prueba. Una fila por familia que confirmo la
 * lectura. Sin esta tabla, "lo recibió" es una suposición.
 *
 * Un aviso a un curso de 35 alumnos genera 35 filas en destinatarios, que es
 * lo correcto: cada familia se enteró por su cuenta y puede confirmar por su
 * cuenta.
 *
 * INDICES
 * -------
 * El indice de `comunicacion_recibos` cubre (comunicacion_id, apoderado_id)
 * y es único: un acuse por familia y aviso. Si la familia confirma dos
 * veces, la segunda no duplica el registro, solo actualiza la fecha.
 *
 * AISLAMIENTO: las tres tablas llevan `tenant_id` y reciben Row Level
 * Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicaciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            // Referencia corta y dictada por la recepción, por ejemplo
            // "COM-2026-000137".
            $tabla->string('referencia', 30);

            $tabla->uuid('plantilla_id')->nullable();
            $tabla->foreign('plantilla_id')->references('id')->on('plantillas_comunicacion')->nullOnDelete();

            $tabla->string('titulo', 180);
            $tabla->text('cuerpo');

            // "correo", "whatsapp", "presencial", "telefono", "web".
            $tabla->string('canal', 30);

            // "curso", "nivel", "apoderado", "alumno".
            $tabla->string('tipo_destinatario', 30);

            // Solo se usa uno de los cuatro segun `tipo_destinatario`. La
            // validacion de que el par corresponda al tipo va en el
            // FormRequest, no aca: la base no debe impedir avisos validos
            // de un curso que tambien pertenece a un nivel.
            $tabla->uuid('curso_id')->nullable();
            $tabla->foreign('curso_id')->references('id')->on('cursos')->nullOnDelete();
            $tabla->uuid('nivel_id')->nullable();
            $tabla->foreign('nivel_id')->references('id')->on('niveles')->nullOnDelete();
            $tabla->uuid('alumno_id')->nullable();
            $tabla->foreign('alumno_id')->references('id')->on('alumnos')->nullOnDelete();
            $tabla->uuid('apoderado_id')->nullable();
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->nullOnDelete();

            $tabla->uuid('remitente_usuario_id');
            $tabla->foreign('remitente_usuario_id')->references('id')->on('usuarios')->restrictOnDelete();

            // Cuando es `true`, la familia tiene que confirmar lectura. Un
            // aviso sin acuse sigue siendo valido (no todo aviso lo
            // necesita); lo que no se permite es exigir acuse y no
            // registrarlo.
            $tabla->boolean('requiere_acuse')->default(false);

            // "borrador" o "enviada".
            $tabla->string('estado', 20)->default('borrador');
            $tabla->timestampTz('enviada_en')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'referencia']);

            // El indice que usa el listado: comunicaciones de un curso, de
            // mas reciente a mas antigua.
            $tabla->index(['tenant_id', 'curso_id', 'enviada_en']);
            $tabla->index(['tenant_id', 'enviada_en']);
        });

        Schema::create('comunicacion_destinatarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('comunicacion_id');
            $tabla->foreign('comunicacion_id')->references('id')->on('comunicaciones')->cascadeOnDelete();
            $tabla->uuid('apoderado_id');
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->cascadeOnDelete();

            $tabla->timestampsTz();

            // La misma familia no aparece dos veces en un mismo aviso.
            $tabla->unique(['tenant_id', 'comunicacion_id', 'apoderado_id']);

            // Indice inverso: "todos los avisos dirigidos a esta familia".
            $tabla->index(['tenant_id', 'apoderado_id']);
        });

        Schema::create('comunicacion_recibos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();
            $tabla->uuid('comunicacion_id');
            $tabla->foreign('comunicacion_id')->references('id')->on('comunicaciones')->cascadeOnDelete();
            $tabla->uuid('apoderado_id');
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->cascadeOnDelete();

            $tabla->timestampTz('recibido_en');

            // "web", "correo", "whatsapp", "telefono".
            $tabla->string('medio', 30);

            $tabla->ipAddress('direccion_ip')->nullable();

            $tabla->timestampsTz();

            // Un acuse por familia y aviso. Si la familia confirma otra
            // vez, se actualiza la fila en lugar de duplicarla.
            $tabla->unique(['tenant_id', 'comunicacion_id', 'apoderado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacion_recibos');
        Schema::dropIfExists('comunicacion_destinatarios');
        Schema::dropIfExists('comunicaciones');
    }
};