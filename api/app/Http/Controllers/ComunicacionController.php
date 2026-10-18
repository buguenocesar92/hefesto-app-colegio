<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoComunicacion;
use App\Enums\TipoDestinatario;
use App\Http\Requests\RegistrarAcuseRequest;
use App\Http\Requests\StoreComunicacionRequest;
use App\Http\Resources\ComunicacionResource;
use App\Models\Comunicacion;
use App\Models\ComunicacionRecibo;
use App\Services\Comunicaciones\GeneradorDeReferencia;
use App\Services\Comunicaciones\ResolvedorDeDestinatarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * EL MÓDULO CENTRAL: comunicaciones con las familias.
 *
 * QUÉ RESUELVE
 * ------------
 * "Cuando una familia pregunta qué le avisaron a su hijo, y se lo podemos
 * mostrar con fecha, canal, contenido, a quién se lo mandamos y quién lo
 * confirmó."
 *
 * LO QUE ESTE CONTROLADOR NO HACE
 * -------------------------------
 * No envía nada. Registra lo que el colegio ya envió. Enviar de verdad por
 * correo o WhatsApp es otro módulo, con otro alcance y con sus propias
 * obligaciones de consentimiento (docs/09).
 *
 * DOBLE DEFENSA
 * -------------
 * La Policy decide qué puede hacer la persona y la RLS que nada sale del
 * colegio. Un identificador de comunicación de otro colegio llega aquí como
 * `null` (404), y aunque llegara, la base no lo devolvería.
 */
class ComunicacionController extends Controller
{
    public function __construct(
        private readonly GeneradorDeReferencia $referencias,
        private readonly ResolvedorDeDestinatarios $destinatarios,
    ) {}

    /**
     * Listado de comunicaciones del colegio.
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('verListado', Comunicacion::class);

        $consulta = Comunicacion::query()
            ->with(['curso', 'nivel', 'remitente'])
            ->orderByDesc('created_at');

        if ($curso = $request->query('curso_id')) {
            $consulta->where('curso_id', $curso);
        }

        if ($estado = $request->query('estado')) {
            $consulta->where('estado', $estado);
        }

        if ($canal = $request->query('canal')) {
            $consulta->where('canal', $canal);
        }

        // Avisos que aún esperan acuse: es la lista de trabajo del maestro
        // de comunicaciones.
        if ($request->boolean('pendientes')) {
            $consulta->where('requiere_acuse', true)
                ->where('estado', EstadoComunicacion::Enviada->value);
        }

        $busqueda = trim((string) $request->query('q', ''));

        if ($busqueda !== '') {
            $consulta->where(function ($interna) use ($busqueda): void {
                $interna->where('titulo', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('referencia', 'ilike', '%' . $busqueda . '%');
            });
        }

        $pagina = $consulta->paginate($this->tamanoDePagina($request))->withQueryString();

        return ComunicacionResource::collection($pagina);
    }

    /**
     * Detalle de una comunicación, con su entrega.
     */
    public function show(Request $request, Comunicacion $comunicacion): ComunicacionResource
    {
        $this->authorize('ver', $comunicacion);

        $comunicacion->load(['curso', 'nivel', 'remitente', 'destinatarios.apoderado', 'recibos.apoderado']);

        return ComunicacionResource::make($comunicacion);
    }

    /**
     * Alta de una comunicación.
     *
     * `enviar=true` cierra el ciclo: resuelve los destinatarios y marca la
     * fecha de envío. Un borrador queda guardado sin destinatarios, porque
     * la lista se resuelve al enviar y no antes.
     */
    public function store(StoreComunicacionRequest $request): JsonResponse
    {
        $this->authorize('crear', Comunicacion::class);

        $datos = $request->validated();
        $usuario = $request->user();

        $tipo = TipoDestinatario::from($datos['tipo_destinatario']);

        $enviar = (bool) ($datos['enviar'] ?? true);

        $comunicacion = Comunicacion::create([
            'tenant_id' => $usuario->tenant_id,
            'referencia' => $this->referencias->siguiente($usuario->tenant_id),
            'plantilla_id' => $datos['plantilla_id'] ?? null,
            'titulo' => $datos['titulo'],
            'cuerpo' => $datos['cuerpo'],
            'canal' => $datos['canal'],
            'tipo_destinatario' => $tipo->value,
            'curso_id' => $tipo === TipoDestinatario::Curso ? $datos['curso_id'] : null,
            'nivel_id' => $tipo === TipoDestinatario::Nivel ? $datos['nivel_id'] : null,
            'alumno_id' => $tipo === TipoDestinatario::Alumno ? $datos['alumno_id'] : null,
            'apoderado_id' => $tipo === TipoDestinatario::Apoderado ? $datos['apoderado_id'] : null,
            'remitente_usuario_id' => $usuario->id,
            'requiere_acuse' => (bool) ($datos['requiere_acuse'] ?? false),
            'estado' => $enviar ? EstadoComunicacion::Enviada->value : EstadoComunicacion::Borrador->value,
            'enviada_en' => $enviar ? now() : null,
        ]);

        $totalDestinatarios = 0;

        if ($enviar) {
            $apoderadoIds = $this->destinatarios->resolver(
                $tipo,
                $datos['curso_id'] ?? null,
                $datos['nivel_id'] ?? null,
                $datos['alumno_id'] ?? null,
                $datos['apoderado_id'] ?? null,
            );

            // Un aviso a un curso sin familias matriculadas no es un error:
            // es un aviso que no tenía a quién llegar, y el colegio tiene que
            // enterarse de eso en vez de creer que salió.
            if ($apoderadoIds === []) {
                throw ValidationException::withMessages([
                    'tipo_destinatario' => 'No se encontró ninguna familia destinataria para este aviso.',
                ]);
            }

            $totalDestinatarios = $this->destinatarios->registrar($comunicacion, $apoderadoIds);
        }

        $comunicacion->load(['curso', 'nivel', 'remitente', 'destinatarios.apoderado', 'recibos']);

        return response()->json([
            'datos' => ComunicacionResource::make($comunicacion)->resolve($request),
            'mensaje' => $enviar
                ? sprintf('Comunicación enviada a %d familia(s).', $totalDestinatarios)
                : 'Borrador guardado. Los destinatarios se resuelven al enviar.',
        ], 201);
    }

    /**
     * Envía un borrador ya guardado.
     */
    public function enviar(Request $request, Comunicacion $comunicacion): JsonResponse
    {
        $this->authorize('crear', $comunicacion);

        if ($comunicacion->fueEnviada()) {
            throw ValidationException::withMessages([
                'estado' => 'Esta comunicación ya fue enviada. Envíe otra si necesita corregir algo.',
            ]);
        }

        $tipo = $comunicacion->tipo_destinatario;

        $apoderadoIds = $this->destinatarios->resolver(
            $tipo,
            $comunicacion->curso_id,
            $comunicacion->nivel_id,
            $comunicacion->alumno_id,
            $comunicacion->apoderado_id,
        );

        if ($apoderadoIds === []) {
            throw ValidationException::withMessages([
                'estado' => 'No se encontró ninguna familia destinataria para este aviso.',
            ]);
        }

        $comunicacion->forceFill([
            'estado' => EstadoComunicacion::Enviada->value,
            'enviada_en' => now(),
        ])->save();

        $total = $this->destinatarios->registrar($comunicacion, $apoderadoIds);

        return response()->json([
            'datos' => ComunicacionResource::make($comunicacion->fresh()->load('destinatarios.apoderado'))->resolve($request),
            'mensaje' => sprintf('Comunicación enviada a %d familia(s).', $total),
        ]);
    }

    /**
     * Acuses de una comunicación.
     *
     * Lista quién confirmó y quién no. Es la pantalla que responde "¿se
     * enteró esta familia?".
     */
    public function recibos(Request $request, Comunicacion $comunicacion): JsonResponse
    {
        $this->authorize('verAcuses', $comunicacion);

        $destinatarios = $comunicacion->destinatarios()->with('apoderado')->get();
        $recibos = $comunicacion->recibos()->get()->keyBy('apoderado_id');

        return response()->json([
            'referencia' => $comunicacion->referencia,
            'total_destinatarios' => $destinatarios->count(),
            'total_recibos' => $recibos->count(),
            'pendientes' => $destinatarios->count() - $recibos->count(),
            'porcentaje_acuse' => $comunicacion->porcentajeAcuse(),
            'detalle' => $destinatarios->map(function ($destinatario) use ($recibos): array {
                $recibo = $recibos->get($destinatario->apoderado_id);

                return [
                    'apoderado_id' => $destinatario->apoderado_id,
                    'nombre_completo' => $destinatario->apoderado?->nombreCompleto(),
                    'recibido' => $recibo !== null,
                    'recibido_en' => $recibo?->recibido_en?->toIso8601String(),
                    'medio' => $recibo?->medio,
                ];
            })->values(),
        ]);
    }

    /**
     * Registra el acuse de una familia.
     *
     * Un acuse por familia y aviso, garantizado por el índice único: si se
     * confirma dos veces, se actualiza la fecha en vez de duplicar. Dos
     * acuses del mismo aviso no dicen más que uno.
     */
    public function registrarAcuse(
        RegistrarAcuseRequest $request,
        Comunicacion $comunicacion,
    ): JsonResponse {
        $this->authorize('registrarAcuse', $comunicacion);

        $datos = $request->validated();

        $apoderadoId = $datos['apoderado_id'] ?? $request->user()->apoderado_id;

        if ($apoderadoId === null) {
            throw ValidationException::withMessages([
                'apoderado_id' => 'No se pudo determinar a qué familia corresponde el acuse.',
            ]);
        }

        // El acuse solo existe para un aviso que se envió a esa familia. Sin
        // esta comprobación se podría "acusar" un aviso dirigido a otro curso,
        // y el conteo de entrega dejaría de significar nada.
        $esDestinatario = DB::table('comunicacion_destinatarios')
            ->where('comunicacion_id', $comunicacion->id)
            ->where('apoderado_id', $apoderadoId)
            ->exists();

        if (! $esDestinatario) {
            throw ValidationException::withMessages([
                'apoderado_id' => 'Esta familia no fue destinataria de esta comunicación.',
            ]);
        }

        $recibo = ComunicacionRecibo::query()->updateOrCreate(
            [
                'tenant_id' => $comunicacion->tenant_id,
                'comunicacion_id' => $comunicacion->id,
                'apoderado_id' => $apoderadoId,
            ],
            [
                'recibido_en' => $datos['recibido_en'] ?? now(),
                'medio' => $datos['medio'],
                'direccion_ip' => $request->ip(),
            ]
        );

        return response()->json([
            'datos' => [
                'id' => $recibo->id,
                'comunicacion_id' => $recibo->comunicacion_id,
                'apoderado_id' => $recibo->apoderado_id,
                'recibido_en' => $recibo->recibido_en?->toIso8601String(),
                'medio' => $recibo->medio,
            ],
            'entrega' => [
                'total_destinatarios' => $comunicacion->destinatarios()->count(),
                'total_recibos' => $comunicacion->recibos()->count(),
                'porcentaje_acuse' => $comunicacion->porcentajeAcuse(),
            ],
            'mensaje' => 'Acuse de recibo registrado.',
        ], 201);
    }

    /**
     * Tamaño de página acotado, para que nadie pida diez mil filas.
     */
    private function tamanoDePagina(Request $request): int
    {
        $pedido = (int) $request->query('per_page', 15);

        return max(1, min($pedido, 50));
    }
}