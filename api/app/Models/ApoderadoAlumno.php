<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre apoderado y alumno.
 *
 * Tabla de datos, pero con consecuencias de seguridad: es la fuente de
 * verdad de "esta familia tiene este hijo". Ver migración
 * 2026_10_17_000006.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ApoderadoAlumno extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'apoderado_alumnos';

    protected $fillable = [
        'tenant_id',
        'apoderado_id',
        'alumno_id',
        'vinculo',
        'es_responsable_legal',
        'es_contacto_emergencia',
    ];

    protected function casts(): array
    {
        return [
            'es_responsable_legal' => 'boolean',
            'es_contacto_emergencia' => 'boolean',
        ];
    }

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}