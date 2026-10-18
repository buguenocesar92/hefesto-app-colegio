<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoMatricula;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matrícula de un alumno en un curso, en un año.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Matricula extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'matriculas';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'curso_id',
        'anio_escolar',
        'numero_matricula',
        'estado',
        'fecha_matricula',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoMatricula::class,
            'anio_escolar' => 'integer',
            'fecha_matricula' => 'date',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function estaActiva(): bool
    {
        return $this->estado?->activa() ?? false;
    }
}