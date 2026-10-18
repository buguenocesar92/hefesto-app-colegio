<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalComunicacion;
use App\Enums\EstadoComunicacion;
use App\Enums\TipoDestinatario;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EL MÓDULO CENTRAL: una comunicación enviada a las familias.
 *
 * QUÉ ES Y QUÉ NO ES
 * ------------------
 * Es el REGISTRO de lo que el colegio ya envió. Este modelo no envía
 * nada: no hay cliente de correo, no hay integración con WhatsApp. Guardar
 * un aviso aquí significa "esto ya se mandó, por acá, a estas familias".
 *
 * Por eso el estado terminal es `enviada` y una comunicación enviada no se
 * edita. Editar el texto de algo ya entregado dejaría de probar qué se le
 * dijo a la familia, que es la razón de existir del módulo.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Comunicacion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'comunicaciones';

    protected $fillable = [
        'tenant_id',
        'referencia',
        'plantilla_id',
        'titulo',
        'cuerpo',
        'canal',
        'tipo_destinatario',
        'curso_id',
        'nivel_id',
        'alumno_id',
        'apoderado_id',
        'remitente_usuario_id',
        'requiere_acuse',
        'estado',
        'enviada_en',
    ];

    protected function casts(): array
    {
        return [
            'canal' => CanalComunicacion::class,
            'tipo_destinatario' => TipoDestinatario::class,
            'estado' => EstadoComunicacion::class,
            'requiere_acuse' => 'boolean',
            'enviada_en' => 'datetime',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaComunicacion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    /** Destino de un aviso dirigido a un alumno concreto. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** Destino de un aviso dirigido a una familia concreta. */
    public function apoderadoDestinatario(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class, 'apoderado_id');
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'remitente_usuario_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ComunicacionDestinatario::class);
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(ComunicacionRecibo::class);
    }

    public function incidentes(): HasMany
    {
        return $this->hasMany(IncidenteConducta::class);
    }

    /** ¿Cuántas familias falta por confirmar? */
    public function pendientesDeAcuse(): int
    {
        return $this->destinatarios()->count() - $this->recibos()->count();
    }

    /** Porcentaje de acuses, de 0 a 100. */
    public function porcentajeAcuse(): int
    {
        $destinatarios = $this->destinatarios()->count();

        if ($destinatarios === 0) {
            return 0;
        }

        return (int) round($this->recibos()->count() * 100 / $destinatarios);
    }

    public function fueEnviada(): bool
    {
        return $this->estado === EstadoComunicacion::Enviada;
    }
}