<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista pública de colegios.
 *
 * `colegios` NO lleva RLS, porque se consulta durante el acceso, cuando
 * todavía no hay contexto de tenant. Esa es una excepción consciente, y esta
 * vista es la primera de sus dos compensaciones.
 *
 * ANTES DEL ACCESO, la aplicación lee ESTA vista, no la tabla. Expone solo
 * tres columnas:
 *
 *  - `slug`: el identificador de la URL de entrada.
 *  - `nombre`: el nombre del colegio.
 *  - `activo`: para no ofrecer el ingreso de un colegio suspendido.
 *
 * NO expone razón social, RUT, dominio de correo, correo de contacto ni
 * teléfono. Conceder el dominio de correo antes del acceso ya sería una
 * enumeración: cualquiera podría recorrer la tabla y descubrir qué
 * dominios usa cada colegio.
 *
 * La segunda compensación es que el rol de la aplicación no es superusuario
 * ni tiene BYPASSRLS. Lo comprueba `php artisan colegio:verificar-aislamiento`.
 *
 * ADVERTENCIA HONESTA: esta vista NO se ejecutó contra PostgreSQL real.
 * Ver docs/10.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_colegios_publicos AS
            SELECT
                c.slug,
                c.nombre,
                c.activo
            FROM public.colegios c
            WHERE c.activo = true;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON VIEW public.vw_colegios_publicos IS
            'Vista pública de colegios para el acceso. Expone solo slug, nombre y activo. No usar para nada que requiera más datos.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_colegios_publicos');
    }
};