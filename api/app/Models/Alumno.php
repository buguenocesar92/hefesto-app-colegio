<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Alumno.
 *
 * MODELO DEL ALCANCE
 * ------------------
 * Esta clase NO tiene una relación con `apoderados` que devuelva "los
 * apoderados de este alumno", ni un atributo que diga quién puede verlo. A
 * propósito. El alcance no se deduce de los datos: lo decide
 * `AlcanceDeAlumnos`, en un solo lugar, y todas las consultas de listado
 * pasan por ahí.
 *
 * Lo que SÍ se expone son relaciones de datos (matrícula, contactos,
 * incidentes), que no deciden autorización.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Alumno extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'alumnos';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'apellido',
        'rut',
        'fecha_nacimiento',
        'sexo',
        'direccion',
        'telefono',
        'correo',
        'fecha_ingreso',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    /**
     * Matrícula vigente.
     *
     * Es la que se usa en la interfaz. La historial completo de matrículas
     * (años anteriores) está en la relación hasMany.
     */
    public function matricula(): HasOne
    {
        return $this->hasOne(Matricula::class)->where('estado', 'matriculado');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function contactosEmergencia(): HasMany
    {
        return $this->hasMany(ContactoEmergencia::class)->orderBy('prioridad');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function incidentes(): HasMany
    {
        return $this->hasMany(IncidenteConducta::class);
    }

    public function entrevistas(): HasMany
    {
        return $this->hasMany(Entrevista::class);
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class);
    }

    /** Vínculos con apoderados. Datos, no autorización. */
    public function vinculos(): HasMany
    {
        return $this->hasMany(ApoderadoAlumno::class);
    }

    public function apoderados(): BelongsToMany
    {
        return $this->belongsToMany(Apoderado::class, 'apoderado_alumnos')
            ->withPivot(['vinculo', 'es_responsable_legal', 'es_contacto_emergencia'])
            ->withTimestamps();
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    /** Edad en años cumplidos. */
    public function edad(): int
    {
        return $this->fecha_nacimiento?->diffInYears(now()) ?? 0;
    }

    /** Iniciales para las planillas: "C. Pérez". */
    public function iniciales(): string
    {
        return mb_strtoupper(mb_substr($this->nombre, 0, 1) . '. ' . $this->apellido);
    }
}