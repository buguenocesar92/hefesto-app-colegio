<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Colegio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colegio>
 */
class ColegioFactory extends Factory
{
    protected $model = Colegio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'nombre' => fake()->company() . ' Colegio',
            'razon_social' => fake()->company() . ' SpA',
            'rut' => null,
            'dominio_correo' => $slug . '.cl',
            'correo_contacto' => 'contacto@' . $slug . '.cl',
            'telefono_contacto' => '+56 2 2xxx 0000',
            'activo' => true,
        ];
    }

    /** Colegio suspendido: no debe aparecer en la vista pública. */
    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}