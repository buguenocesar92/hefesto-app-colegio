<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->firstNameFemale();
        $apellido = fake()->lastName();

        return [
            // El `tenant_id` lo pone siempre quien crea el dato. Se deja
            // `tenant_id` fuera del definition a propósito: una fábrica que
            // genera un tenant_id propio haría más fácil crear filas en el
            // colegio equivocado.
            'nombre' => $nombre,
            'apellido' => $apellido,
            'rut' => $this->rut(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-16 years', '-6 years'),
            'sexo' => 'femenino',
            'direccion' => fake()->streetAddress() . ', ' . fake()->city(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'correo' => null,
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now'),
            'activo' => true,
        ];
    }

    /** RUT chileno sintácticamente válido (con dígito verificador). */
    private function rut(): string
    {
        $cuerpo = fake()->numerify('########');
        $dv = $this->digitoVerificador($cuerpo);

        return substr($cuerpo, 0, 2) . '.' . substr($cuerpo, 2, 3) . '.' . substr($cuerpo, 5, 3) . '-' . $dv;
    }

    private function digitoVerificador(string $cuerpo): int
    {
        $suma = 0;

        for ($i = 0; $i < strlen($cuerpo); $i++) {
            $suma += ((int) $cuerpo[$i]) * (2 + ($i % 7));
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => 0,
            10 => 'K',
            default => $resto,
        };
    }
}