<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos: registro de quién abrió qué dato.
 *
 * Modo append-only. No hay interfaz de lectura: se consulta por consola.
 *
 * POR QUÉ ESTA TABLA EXISTE CUANDO HAY RLS
 * ----------------------------------------
 * La RLS impide que un colegio vea datos de otro. No impide que un empleado
 * del colegio mire la ficha de un alumno que no le corresponde. Eso lo
 * resuelven las Policies, pero una Policy correcta y una Policy que alguien
 * saltó se ven iguales desde la interfaz. La bitácora es lo que permite
 * responder "¿quién abrió la ficha de este alumno?".
 *
 * Es lo que la Ley 19.628 exige poder demostrar: quién accede a datos
 * personales y con qué finalidad.
 *
 * `exito` importa tanto como `codigo_estado`: un 404 sobre un id de otro
 * colegio tambien es un intento.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_accesos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            // Nulo cuando la peticion se registro sin sesion.
            $tabla->uuid('usuario_id')->nullable();
            $tabla->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();

            // Que se hizo, en pocas palabras: "ver_alumno", "crear_comunicacion".
            $tabla->string('accion', 80);

            $tabla->string('ruta', 255);
            $tabla->string('metodo', 10);
            $tabla->ipAddress('direccion_ip')->nullable();
            $tabla->text('agente_usuario')->nullable();

            $tabla->unsignedSmallInteger('codigo_estado');
            $tabla->boolean('exito')->default(true);
            $tabla->timestampTz('registrado_en');

            $tabla->index(['tenant_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'usuario_id', 'registrado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_accesos');
    }
};