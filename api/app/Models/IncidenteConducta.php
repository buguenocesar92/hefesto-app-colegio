<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaIncidente;
use App\Enums\GravedadIncidente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CONDUCTA: incidente registrado sobre un alumno. Tabla propia, separada de
 * asistencia.
 *
 * El dato más sensible del sistema después del nombre. Por eso tiene su
 * propia Policy, su propio alcance y su propia columna para enlazar la
 * comunicación que se le envió a la familia.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class IncidenteConducta extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'conducta_incidentes';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'curso_id',
        'fecha',
        'categoria',
        'gravedad',
        'descripcion',
        'medida',
        'registrado_por_usuario_id',
        'registrado_en',
        'notificado_apoderado',
        'comunicacion_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'categoria' => CategoriaIncidente::class,
            'gravedad' => GravedadIncidente::class,
            'registrado_en' => 'datetime',
            'notificado_apoderado' => 'boolean',
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

    /** La comunicación con la que se avisó a la familia, si se avisó. */
    public function comunicacion(): BelongsTo
    {
        return $this->belongsTo(Comunicacion::class);
    }

    /** ¿La gravedad obliga a comunicarlo a la familia? */
    public function exigeComunicacion(): bool
    {
        return $this->gravedad?->exigeComunicacion() ?? false;
    }
}