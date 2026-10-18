<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEntrevista;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Entrevista o reunión con una familia.
 *
 * `apoderado_id` admite nulo porque hay reuniones de curso a las que asiste
 * el curso y no una familia en concreto.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Entrevista extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'entrevistas';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'apoderado_id',
        'profesional_id',
        'tipo',
        'fecha',
        'hora',
        'lugar',
        'estado',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoEntrevista::class,
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

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function acta(): HasOne
    {
        return $this->hasOne(ActaReunion::class);
    }
}