<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nivel>
 */
class NivelFactory extends Factory
{
    protected $model = Nivel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Básica', 'Media', 'Parvularia']),
            'orden' => fake()->numberBetween(0, 3),
            'activo' => true,
        ];
    }
}