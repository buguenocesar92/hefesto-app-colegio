<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro del consentimiento para tratar datos personales.
 *
 * Ver migración 2026_10_17_000009 y docs/07.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Consentimiento extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consentimientos';

    protected $fillable = [
        'tenant_id',
        'apoderado_id',
        'alumno_id',
        'alcance',
        'version_documento',
        'otorgado_por',
        'fecha_otorgamiento',
        'evidencia_ruta',
        'revocado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_otorgamiento' => 'date',
            'revocado_en' => 'datetime',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    /** Nulo cuando el consentimiento cubre a todos los hijos. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** ¿Está vigente? */
    public function vigente(): bool
    {
        return $this->revocado_en === null;
    }
}