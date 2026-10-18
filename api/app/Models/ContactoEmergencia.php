<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto de emergencia.
 *
 * NO es un apoderado. Son datos de terceros sin relación administrativa con
 * el colegio. Ver migración 2026_10_17_000008.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ContactoEmergencia extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'contactos_emergencia';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'nombre',
        'parentesco',
        'telefono',
        'prioridad',
    ];

    protected function casts(): array
    {
        return ['prioridad' => 'integer'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}