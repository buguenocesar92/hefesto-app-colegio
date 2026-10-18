<?php

declare(strict_types=1);

use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asistencia;
use App\Models\Comunicacion;
use App\Models\IncidenteConducta;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crea un colegio nuevo con lo mínimo para poder entrar.
 *
 * POR QUÉ ESTE COMANDO Y NO UN ALTA EN LA INTERFAZ
 * -----------------------------------------------
 * Para tener un colegio hace falta un usuario de Dirección, y para tener un
 * usuario de Dirección hace falta una identidad en Authentik, que no se puede
 * crear desde acá. Un orden que empieza por la interfaz no tiene retorno.
 *
 * Este comando deja el colegio listo: estructura académica mínima y el
 * primer usuario con `authentik_sub` vacío, que hay que completar con el
 * valor real de Authentik antes de que esa persona pueda entrar.
 *
 *   php artisan colegio:crear
 *
 * ADVERTENCIA: se escribe en tablas con RLS, así que todo corre dentro del
 * contexto del colegio que se está creando.
 */
class CrearColegioCommand extends Command
{
    protected $signature = 'colegio:crear
        {--nombre= : Nombre del colegio}
        {--slug= : Identificador corto para la URL}
        {--dominio= : Dominio de correo declarado}
        {--direccion= : Nombre de quien será la dirección}
        {--correo= : Correo de quien será la dirección}';

    protected $description = 'Crea un colegio con su estructura académica mínima y el primer usuario de dirección.';

    public function handle(): int
    {
        $nombre = (string) ($this->option('nombre') ?: $this->ask('Nombre del colegio'));
        $slug = (string) ($this->option('slug') ?: Str::slug($nombre));
        $dominio = (string) ($this->option('dominio') ?: $this->ask('Dominio de correo declarado'));
        $correoDireccion = (string) ($this->option('correo') ?: $this->ask('Correo de la dirección'));

        if ($nombre === '' || $dominio === '' || $correoDireccion === '') {
            $this->error('Falta el nombre, el dominio o el correo de la dirección.');

            return self::FAILURE;
        }

        $existente = \App\Models\Colegio::query()
            ->where('slug', $slug)
            ->orWhere('dominio_correo', $dominio)
            ->first();

        if ($existente !== null) {
            $this->error('Ya existe un colegio con ese identificador o ese dominio.');

            return self::FAILURE;
        }

        $colegio = \App\Models\Colegio::create([
            'slug' => $slug,
            'nombre' => $nombre,
            'dominio_correo' => $dominio,
            'correo_contacto' => $correoDireccion,
            'activo' => true,
        ]);

        $contexto = new ContextoTenant();

        $contexto->ejecutarAislado($colegio->id, function () use ($colegio, $nombre, $correoDireccion): void {
            $basico = \App\Models\Nivel::create([
                'tenant_id' => $colegio->id,
                'nombre' => 'Básica',
                'orden' => 1,
                'activo' => true,
            ]);

            \App\Models\Nivel::create([
                'tenant_id' => $colegio->id,
                'nombre' => 'Media',
                'orden' => 2,
                'activo' => true,
            ]);

            \App\Models\Curso::create([
                'tenant_id' => $colegio->id,
                'nivel_id' => $basico->id,
                'nombre' => '1° Básico A',
                'sigla' => '1A',
                'orden' => 1,
                'activo' => true,
            ]);

            \App\Models\Usuario::create([
                'tenant_id' => $colegio->id,
                // Vacío a propósito: hay que completarlo con el `sub` real
                // de Authentik. Sin eso, la persona no puede entrar.
                'authentik_sub' => '',
                'correo' => $correoDireccion,
                'nombre' => 'Dirección',
                'apellido' => $colegio->nombre,
                'rol' => \App\Enums\RolUsuario::Direccion,
                'activo' => true,
            ]);
        });

        $this->info(sprintf('Colegio "%s" creado con el identificador "%s".', $nombre, $slug));
        $this->line('');
        $this->warn('Falta lo más importante: crear el usuario en Authentik con ese correo,');
        $this->warn('entrar una vez por la URL de ingreso y completar `authentik_sub` con el');
        $this->warn('valor `sub` del token. Sin ese paso, nadie puede entrar.');

        return self::SUCCESS;
    }
}