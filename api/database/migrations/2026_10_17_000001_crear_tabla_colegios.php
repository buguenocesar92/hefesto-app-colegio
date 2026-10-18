<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `colegios`: el tenant.
 *
 * AISLAMIENTO
 * -----------
 * Esta tabla NO lleva `tenant_id` ni Row Level Security, a proposito: es la
 * que se consulta durante el acceso, cuando todavia no hay sesion y por lo
 * tanto no hay contexto que poner en `app.tenant_id`. Se compensa con dos
 * medidas descritas en docs/03:
 *
 *  1. Antes del acceso la aplicacion lee `vw_colegios_publicos`, que expone
 *     solo tres columnas.
 *  2. El rol de base de datos de la aplicacion no es superusuario ni tiene
 *     BYPASSRLS. Lo comprueba `php artisan colegio:verificar-aislamiento`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colegios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            // Identificador corto que aparece en las URL de entrada. No es
            // un secreto: se reparte en los enlaces que envia el colegio.
            $tabla->string('slug', 80)->unique();

            $tabla->string('nombre', 160);
            $tabla->string('razon_social', 180)->nullable();
            $tabla->string('rut', 20)->nullable();

            // Dominio de correo declarado. Se usa para resolver el colegio
            // de una cuenta recien creada.
            $tabla->string('dominio_correo', 120)->unique();

            $tabla->string('correo_contacto', 180)->nullable();
            $tabla->string('telefono_contacto', 40)->nullable();
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colegios');
    }
};