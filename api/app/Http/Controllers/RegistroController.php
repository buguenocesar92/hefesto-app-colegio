<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoMatricula;
use App\Enums\GravedadIncidente;
use App\Http\Requests\StoreAsistenciaRequest;
use App\Http\Requests\StoreIncidenteRequest;
use App\Http\Resources\AsistenciaResource;
use App\Http\Resources\IncidenteResource;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\IncidenteConducta;
use App\Services\Alumnos\AlcanceDeAlumnos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Asistencia y conducta.
 *
 * DOS MÓDULOS, UN ARCHIVO
 * -----------------------
 * Los dos endpoints viven en el mismo controlador porque comparten el mismo
 * alcance de alumnos y las mismas reglas de validación, pero NO comparten
 * ni tabla, ni Policy, ni permiso.
 *
 * La diferencia que importa:
 *
 *  - ASISTENCIA: la registra el docente del curso. El apoderado la lee.
 *  - CONDUCTA: la registra el docente o orientación. El apoderado NO la
 *    lee. Y leerla exige un permiso aparte del que hace falta para
 *    registrar.
 *
 * Si en algún momento alguien junta estas dos rutas en un endpoint "porque
 * se parecen", la separación se pierde. Por eso el archivo las mantiene
 * separadas por sección y con Policies distintas.
 */
class RegistroController extends Controller
{
    public function __construct(private readonly AlcanceDeAlumnos $alcance) {}

    // ------------------------------------------------------------------
    // ASISTENCIA
    // ------------------------------------------------------------------

    /**
     * Registro de asistencia de un día, para los alumnos que la persona ve.
     */
    public function asistencias(Request $request): AnonymousResourceCollection
    {
        $this->authorize('verListado', Asistencia::class);

        $fecha = $request->query('fecha', now()->toDateString());
        $cursoId = $request->query('curso_id');

        $consulta = Asistencia::query()
            ->with(['alumno', 'registradoPor'])
            ->where('fecha', $fecha)
            ->whereIn('alumno_id', $this->alcance->apoderablesPor($request->user())->select('alumnos.id'));

        if ($cursoId !== null) {
            $consulta->where('curso_id', $cursoId);
        }

        return AsistenciaResource::collection($consulta->orderBy('alumno_id')->get());
    }

    /**
     * Registra la asistencia de uno o varios alumnos de un curso.
     *
     * Recibe una lista porque el caso real es la hoja completa de un curso,
     * no un alumno suelto.
     */
    public function registrarAsistencia(StoreAsistenciaRequest $request): JsonResponse
    {
        $this->authorize('registrar', Asistencia::class);

        $usuario = $request->user();
        $datos = $request->validated();

        $registradas = [];
        $omitidas = [];

        foreach ($datos['registros'] as $registro) {
            $alumno = Alumno::find($registro['alumno_id']);

            // El filtro de alcance se aplica TAMBIÉN al escribir. Si el
            // docente manda el id de un alumno que no es suyo, se omite y se
            // dice cuál: no es un error del sistema, es un dato que no
            // corresponde.
            if ($alumno === null || ! $this->alcance->puedeRegistrarAsistencia($usuario, $alumno)) {
                $omitidas[] = $registro['alumno_id'];

                continue;
            }

            $registradas[] = $this->upsertAsistencia($datos, $registro, $usuario->id);
        }

        return response()->json([
            'registradas' => $registradas,
            'omitidas' => $omitidas,
            'mensaje' => sprintf(
                'Se registró la asistencia de %d alumno(s).' . ($omitidas === [] ? '' : ' %d quedaron fuera porque no son de sus cursos.'),
                count($registradas),
                count($omitidas)
            ),
        ], 201);
    }

    // ------------------------------------------------------------------
    // CONDUCTA
    // ------------------------------------------------------------------

    /**
     * Incidentes de conducta que la persona puede leer.
     */
    public function incidentes(Request $request): AnonymousResourceCollection
    {
        $this->authorize('verListado', IncidenteConducta::class);

        $consulta = IncidenteConducta::query()
            ->with(['alumno', 'curso', 'registradoPor'])
            ->whereIn('alumno_id', $this->alcance->apoderablesPor($request->user())->select('alumnos.id'))
            ->orderByDesc('fecha');

        if ($alumnoId = $request->query('alumno_id')) {
            $consulta->where('alumno_id', $alumnoId);
        }

        if ($curso = $request->query('curso_id')) {
            $consulta->where('curso_id', $curso);
        }

        if ($gravedad = $request->query('gravedad')) {
            $consulta->where('gravedad', $gravedad);
        }

        return IncidenteResource::collection(
            $consulta->paginate($this->tamanoDePagina($request))->withQueryString()
        );
    }

    /**
     * Registra un incidente de conducta.
     *
     * Devuelve además el aviso de lo que la gravedad obliga a hacer. No
     * manda el aviso: dice que hay que mandarlo. Manda es la decisión de una
     * persona, no de un `if`.
     */
    public function registrarIncidente(StoreIncidenteRequest $request): JsonResponse
    {
        $this->authorize('crear', IncidenteConducta::class);

        $usuario = $request->user();
        $datos = $request->validated();

        $alumno = Alumno::findOrFail($datos['alumno_id']);

        $gravedad = GravedadIncidente::from($datos['gravedad']);

        $incidente = IncidenteConducta::create([
            'tenant_id' => $usuario->tenant_id,
            'alumno_id' => $alumno->id,
            'curso_id' => $datos['curso_id'],
            'fecha' => $datos['fecha'],
            'categoria' => $datos['categoria'],
            'gravedad' => $gravedad->value,
            'descripcion' => $datos['descripcion'],
            'medida' => $datos['medida'] ?? null,
            'registrado_por_usuario_id' => $usuario->id,
            'registrado_en' => now(),
            'notificado_apoderado' => false,
        ]);

        $mensaje = 'Incidente registrado.';

        // Lo que la gravedad obliga aparece en la respuesta, para que quien
        // registró no salga creyendo que ya se resolvió todo.
        if ($gravedad->exigeComunicacion() && ! $incidente->notificado_apoderado) {
            $mensaje .= ' Esta gravedad requiere avisar a la familia: cree la comunicación y enlacela al incidente.';
        }

        if ($gravedad->exigeEntrevista()) {
            $mensaje .= ' Esta gravedad requiere agendar una entrevista con la familia.';
        }

        return response()->json([
            'datos' => IncidenteResource::make($incidente->load(['alumno', 'curso', 'registradoPor']))->resolve($request),
            'mensaje' => $mensaje,
        ], 201);
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    /**
     * Guarda o corrige la asistencia de un alumno.
     *
     * `updateOrCreate` sobre (alumno, fecha), que es lo que garantiza el
     * índice único. Corregir un día ya registrado es una operación normal
     * del colegio (el alumno llegó después de la hora), no un error.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $registro
     * @return array<string, mixed>
     */
    private function upsertAsistencia(array $datos, array $registro, string $usuarioId): array
    {
        $asistencia = Asistencia::query()->updateOrCreate(
            [
                'tenant_id' => $datos['tenant_id'] ?? auth()->user()?->tenant_id,
                'alumno_id' => $registro['alumno_id'],
                'fecha' => $datos['fecha'],
            ],
            [
                'curso_id' => $datos['curso_id'],
                'estado' => $registro['estado'],
                'justificacion' => $registro['justificacion'] ?? null,
                'registrado_por_usuario_id' => $usuarioId,
                'registrado_en' => now(),
            ]
        );

        return [
            'id' => $asistencia->id,
            'alumno_id' => $asistencia->alumno_id,
            'fecha' => $asistencia->fecha?->toDateString(),
            'estado' => $asistencia->estado?->value,
        ];
    }

    private function tamanoDePagina(Request $request): int
    {
        $pedido = (int) $request->query('per_page', 25);

        return max(1, min($pedido, 100));
    }
}