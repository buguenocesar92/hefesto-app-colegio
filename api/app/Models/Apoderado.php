<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apoderado: una persona de la familia.
 *
 * Sus datos identifican a un menor, así que están protegidos como si fueran
 * del alumno. Ver docs/07.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Apoderado extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'apoderados';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'apellido',
        'rut',
        'correo',
        'telefono',
        'direccion',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    /**
     * Hijos del apoderado.
     *
     * La relación que hace que un padre no vea los hijos de otro. Cualquier
     * consulta de alumnos que parta de una cuenta de familia DEBE pasar por
     * acá o por `AlcanceDeAlumnos`.
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'apoderado_alumnos')
            ->withPivot(['vinculo', 'es_responsable_legal', 'es_contacto_emergencia'])
            ->withTimestamps();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(ApoderadoAlumno::class);
    }

    public function comunicaciones(): HasMany
    {
        return $this->hasMany(ComunicacionDestinatario::class);
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }
}