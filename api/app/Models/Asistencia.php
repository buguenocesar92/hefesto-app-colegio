<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAsistencia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ASISTENCIA de un alumno en un día. Tabla propia, separada de conducta.
 *
 * Ver migración 2026_10_17_000014 para por qué están separadas. La regla que
 * más importa: un `null` en `estado` no es una ausencia, es "aún no
 * registrado".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Asistencia extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'asistencias';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'curso_id',
        'fecha',
        'estado',
        'justificacion',
        'registrado_por_usuario_id',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoAsistencia::class,
            'registrado_en' => 'datetime',
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

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

    public function cuentaComoPresente(): bool
    {
        return $this->estado?->cuentaComoPresente() ?? false;
    }
}