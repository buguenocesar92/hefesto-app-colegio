<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de las semillas.
 *
 * Solo se corre a mano, nunca en un despliegue:
 *
 *   php artisan db:seed
 *
 * En producción las semillas NO se ejecutan. Un despliegue que inserta
 * alumnos de ejemplo en la base real es un incidente.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ColegioDemoSeeder::class);

        $this->command?->info('');
        $this->command?->info('Datos de desarrollo cargados.');
        $this->command?->info('');
        $this->command?->warn('Los usuarios de ejemplo NO pueden entrar al sistema:');
        $this->command?->warn('sus identidades en Authentik no existen. Ver docs/04, sección "Datos de desarrollo".');
    }
}