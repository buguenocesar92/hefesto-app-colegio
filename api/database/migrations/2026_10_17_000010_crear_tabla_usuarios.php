<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios: las cuentas que pueden entrar al sistema.
 *
 * Deliberadamente NO hay contrasenas. La identidad la demuestra Authentik
 * (OIDC) y el permiso lo decide el colegio segun el `rol`. Un grupo de
 * Authentik no da permiso permanente: solo define el primer alta.
 *
 * `rol` decide el modelo de permisos, que esta documentado y justificado en
 * docs/03. Los dos vinculos opcionales son los que hacen que el alcance
 * funcione:
 *
 *  - `apoderado_id`: presente solo si el rol es apoderado. Es lo que permite
 *    a `AlcanceDeAlumnos` saber quais hijos puede ver.
 *  - `profesional_id`: presente en roles del colegio. Vincula la cuenta con
 *    la ficha profesional.
 *
 * `authentik_sub` no aparece en ningun Resource: es informacion interna.
 *
 * AISLAMIENTO: lleva `tenant_id` y recibe Row Level Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('colegios')->cascadeOnDelete();

            // Identificador del proveedor de identidad. Es la clave real de la
            // cuenta: el correo puede cambiar, el `sub` no.
            $tabla->string('authentik_sub', 255)->unique();

            $tabla->string('correo', 180);
            $tabla->string('nombre', 80);
            $tabla->string('apellido', 80);
            $tabla->string('telefono', 40)->nullable();

            // direccion, administracion, docente, comunicaciones,
            // orientacion, apoderado.
            $tabla->string('rol', 40);

            $tabla->uuid('profesional_id')->nullable();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->nullOnDelete();

            $tabla->uuid('apoderado_id')->nullable();
            $tabla->foreign('apoderado_id')->references('id')->on('apoderados')->cascadeOnDelete();

            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('ultimo_acceso')->nullable();
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'correo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};