<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoEntrevista;
use App\Http\Resources\ActaResource;
use App\Http\Resources\EntrevistaResource;
use App\Models\ActaReunion;
use App\Models\Alumno;
use App\Models\Entrevista;
use App\Services\Alumnos\AlcanceDeAlumnos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Agenda de entrevistas y reuniones, y sus actas.
 *
 * LA REGLA DE ALCANCE
 * -------------------
 * Agenda es un módulo distinto del de fichas: acá importa "quién puede ver
 * esta entrevista", no "quién puede ver el alumno". Por eso usa
 * `AlcanceDeAlumnos` igual, pero sin exponer la ficha: una entrevista de un
 * alumno de otro curso no aparece.
 *
 * El maestro de comunicaciones SÍ agenda: es parte de su trabajo. Lo que no
 * puede es leer el acta de una entrevista que no trata de su gestión, y eso
 * lo decide `GestionAdministrativaPolicy`.
 */
class EntrevistaController extends Controller
{
    public function __construct(private readonly AlcanceDeAlumnos $alcance) {}

    /**
     * Agenda, con filtros de fecha y curso.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Entrevista::class);

        $consulta = Entrevista::query()
            ->with(['alumno', 'apoderado', 'profesional'])
            ->whereIn('alumno_id', $this->alcance->apoderablesPor($request->user())->select('alumnos.id'))
            ->orderBy('fecha')
            ->orderBy('hora');

        if ($desde = $request->query('desde')) {
            $consulta->whereDate('fecha', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $consulta->whereDate('fecha', '<=', $hasta);
        }

        if ($estado = $request->query('estado')) {
            $consulta->where('estado', $estado);
        }

        return EntrevistaResource::collection($consulta->paginate(25)->withQueryString());
    }

    /**
     * Detalle de una entrevista.
     */
    public function show(Request $request, Entrevista $entrevista): EntrevistaResource
    {
        $this->authorize('ver', $entrevista);

        $entrevista->load(['alumno', 'apoderado', 'profesional', 'acta']);

        return EntrevistaResource::make($entrevista);
    }

    /**
     * Agenda una entrevista.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('crear', Entrevista::class);

        $datos = $request->validate([
            'alumno_id' => ['required', 'uuid'],
            'apoderado_id' => ['nullable', 'uuid'],
            'profesional_id' => ['nullable', 'uuid'],
            'tipo' => ['required', 'string', 'in:entrevista_familiar,reunion,orientacion'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'lugar' => ['nullable', 'string', 'max:160'],
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'alumno_id.required' => 'Indique el alumno.',
            'tipo.required' => 'Indique el tipo de entrevista.',
            'fecha.required' => 'Indique la fecha.',
            'hora.required' => 'Indique la hora.',
            'motivo.required' => 'Describa el motivo de la entrevista.',
            'motivo.min' => 'Describa el motivo con algo más de detalle, para que la familia sepa de qué se trata.',
        ]);

        $alumno = Alumno::findOrFail($datos['alumno_id']);

        // Una entrevista sobre un alumno que la persona no puede ver sería
        // una forma de tener acceso indirecto a la ficha.
        if (! $this->alcance->puedeVerFichaCompleta($request->user(), $alumno)) {
            abort(403, 'No tiene acceso a este alumno.');
        }

        $entrevista = Entrevista::create([
            'tenant_id' => $request->user()->tenant_id,
            'alumno_id' => $alumno->id,
            'apoderado_id' => $datos['apoderado_id'] ?? null,
            'profesional_id' => $datos['profesional_id'] ?? null,
            'tipo' => $datos['tipo'],
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'],
            'lugar' => $datos['lugar'] ?? null,
            'estado' => EstadoEntrevista::Agendada->value,
            'motivo' => $datos['motivo'],
        ]);

        return response()->json([
            'datos' => EntrevistaResource::make($entrevista->load(['alumno', 'apoderado', 'profesional']))->resolve($request),
            'mensaje' => 'Entrevista agendada.',
        ], 201);
    }

    /**
     * Cambia el estado: realizada o cancelada.
     */
    public function cambiarEstado(Request $request, Entrevista $entrevista): JsonResponse
    {
        $this->authorize('actualizar', $entrevista);

        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:realizada,cancelada,agendada'],
        ], [
            'estado.required' => 'Indique el estado nuevo.',
            'estado.in' => 'El estado indicado no es válido.',
        ]);

        $entrevista->forceFill(['estado' => $datos['estado']])->save();

        return response()->json([
            'datos' => EntrevistaResource::make($entrevista->fresh()->load(['alumno', 'apoderado', 'profesional']))->resolve($request),
            'mensaje' => 'Estado de la entrevista actualizado.',
        ]);
    }

    /**
     * Registra el acta de una entrevista.
     */
    public function registrarActa(Request $request, Entrevista $entrevista): JsonResponse
    {
        $this->authorize('actualizar', $entrevista);

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'resumen' => ['required', 'string', 'min:20', 'max:10000'],
            'acuerdos' => ['nullable', 'string', 'max:10000'],
        ], [
            'titulo.required' => 'Póngale un título al acta.',
            'resumen.required' => 'Escriba el resumen de lo conversado.',
            'resumen.min' => 'El resumen es demasiado corto para servir como registro.',
        ]);

        if ($entrevista->acta()->exists()) {
            throw ValidationException::withMessages([
                'titulo' => 'Esta entrevista ya tiene un acta registrada.',
            ]);
        }

        $acta = ActaReunion::create([
            'tenant_id' => $entrevista->tenant_id,
            'entrevista_id' => $entrevista->id,
            'autor_usuario_id' => $request->user()->id,
            'titulo' => $datos['titulo'],
            'resumen' => $datos['resumen'],
            'acuerdos' => $datos['acuerdos'] ?? null,
            'fecha' => $entrevista->fecha,
        ]);

        return response()->json([
            'datos' => ActaResource::make($acta)->resolve($request),
            'mensaje' => 'Acta registrada.',
        ], 201);
    }

    /**
     * Acta de una entrevista.
     */
    public function acta(Request $request, Entrevista $entrevista): ActaResource
    {
        $this->authorize('ver', $entrevista);

        return ActaResource::make($entrevista->acta ?? abort(404, 'Esta entrevista no tiene acta.'));
    }
}