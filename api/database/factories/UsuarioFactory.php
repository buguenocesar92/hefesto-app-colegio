<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->firstNameFemale();
        $apellido = fake()->lastName();

        return [
            'authentik_sub' => (string) Str::uuid(),
            'correo' => Str::slug($nombre . '.' . $apellido, '.') . '.' . Str::random(5) . '@ejemplo.cl',
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'rol' => RolUsuario::Comunicaciones,
            'profesional_id' => null,
            'apoderado_id' => null,
            'activo' => true,
            'ultimo_acceso' => now(),
        ];
    }

    /** Dirección del colegio. */
    public function direccion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Direccion]);
    }

    /** Administración. */
    public function administracion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Administracion]);
    }

    /** Docente. */
    public function docente(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Docente]);
    }

    /** Maestro de comunicaciones. */
    public function comunicaciones(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Comunicaciones]);
    }

    /** Orientación o psicología. */
    public function orientacion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Orientacion]);
    }

    /** Cuenta de familia. */
    public function apoderado(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Apoderado]);
    }

    /** Cuenta desactivada. */
    public function desactivado(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}