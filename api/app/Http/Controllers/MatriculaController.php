<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoMatricula;
use App\Http\Requests\StoreMatriculaRequest;
use App\Http\Resources\MatriculaResource;
use App\Models\Alumno;
use App\Models\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Matrículas: quién entra y cuándo.
 *
 * LA MATRÍCULA ES EL HECHO QUE ABRE TODO
 * --------------------------------------
 * No es solo un registro: es lo que hace que un alumno aparezca en un curso,
 * y por lo tanto lo que hace que su familia reciba los avisos de ese curso.
 * Sin matrícula activa, un alumno no entra en el listado de nadie.
 *
 * Por eso el estado se trata con cuidado: pasar de `matriculado` a
 * `retirado` saca al alumno de los próximos avisos, y eso tiene que ser una
 * decisión, no un efecto colateral.
 */
class MatriculaController extends Controller
{
    /**
     * Listado de matrículas del colegio.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Matricula::class);

        $consulta = Matricula::query()
            ->with(['alumno', 'curso.nivel'])
            ->orderByDesc('anio_escolar');

        if ($anio = $request->query('anio_escolar')) {
            $consulta->where('anio_escolar', (int) $anio);
        } else {
            $consulta->where('anio_escolar', now()->year);
        }

        if ($curso = $request->query('curso_id')) {
            $consulta->where('curso_id', $curso);
        }

        if ($estado = $request->query('estado')) {
            $consulta->where('estado', $estado);
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return MatriculaResource::collection($pagina);
    }

    /**
     * Matricula un alumno en un curso.
     *
     * El número de matrícula lo pone la escuela, no la persona que la está
     * registrando. Puede llegar repetido desde el papel, y eso no puede
     * pasar: la referencia tiene que servir para encontrar a un alumno.
     */
    public function store(StoreMatriculaRequest $request): JsonResponse
    {
        $this->authorize('crear', Matricula::class);

        $datos = $request->validated();
        $usuario = $request->user();

        $alumno = Alumno::findOrFail($datos['alumno_id']);

        $existente = Matricula::query()
            ->where('alumno_id', $alumno->id)
            ->where('anio_escolar', $datos['anio_escolar'])
            ->first();

        if ($existente !== null) {
            throw ValidationException::withMessages([
                'alumno_id' => sprintf(
                    'Este alumno ya tiene una matrícula para el año %d (referencia %s).',
                    $datos['anio_escolar'],
                    $existente->numero_matricula
                ),
            ]);
        }

        $numero = $datos['numero_matricula'] ?? $this->siguienteNumero($usuario->tenant_id, $datos['anio_escolar']);

        if ($this->numeroYaExiste($usuario->tenant_id, $datos['anio_escolar'], $numero)) {
            throw ValidationException::withMessages([
                'numero_matricula' => sprintf('El número "%s" ya está asignado a otro alumno este año.', $numero),
            ]);
        }

        $matricula = Matricula::create([
            'tenant_id' => $usuario->tenant_id,
            'alumno_id' => $alumno->id,
            'curso_id' => $datos['curso_id'],
            'anio_escolar' => $datos['anio_escolar'],
            'numero_matricula' => $numero,
            'estado' => EstadoMatricula::Matriculado->value,
            'fecha_matricula' => $datos['fecha_matricula'] ?? now()->toDateString(),
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        // Una matrícula activa en el colegio implica que el alumno existe en
        // su ficha.
        if (! $alumno->activo) {
            $alumno->forceFill(['activo' => true])->save();
        }

        return response()->json([
            'datos' => MatriculaResource::make($matricula->load(['alumno', 'curso.nivel']))->resolve($request),
            'mensaje' => sprintf('Alumno matriculado con la referencia %s.', $numero),
        ], 201);
    }

    /**
     * Cambia el estado de una matrícula.
     *
     * No se borra nunca: queda el registro de que el alumno estuvo aquí.
     */
    public function cambiarEstado(Request $request, Matricula $matricula): JsonResponse
    {
        $this->authorize('actualizar', $matricula);

        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:pendiente,matriculado,retirado'],
        ], [
            'estado.required' => 'Indique el estado nuevo.',
            'estado.in' => 'El estado indicado no es válido.',
        ]);

        $matricula->forceFill(['estado' => $datos['estado']])->save();

        return response()->json([
            'datos' => MatriculaResource::make($matricula->fresh()->load(['alumno', 'curso']))->resolve($request),
            'mensaje' => 'Estado de la matrícula actualizado.',
        ]);
    }

    /**
     * ¿Este número ya lo tiene alguien en este colegio y año?
     */
    private function numeroYaExiste(string $tenantId, int $anio, string $numero): bool
    {
        return Matricula::query()
            ->where('tenant_id', $tenantId)
            ->where('anio_escolar', $anio)
            ->where('numero_matricula', $numero)
            ->exists();
    }

    /**
     * Propone el siguiente número libre del año.
     *
     * Es una ayuda, no una garantía: la comprobación real es el índice
     * único de la tabla, y dos secretarias matriculando a la vez pueden ver
     * el mismo número. En ese caso el índice lo rechaza y el controlador
     * devuelve un error claro en vez de guardar una referencia repetida.
     */
    private function siguienteNumero(string $tenantId, int $anio): string
    {
        $mayor = Matricula::query()
            ->where('tenant_id', $tenantId)
            ->where('anio_escolar', $anio)
            ->where('numero_matricula', 'like', $anio . '-%')
            ->orderByDesc('numero_matricula')
            ->value('numero_matricula');

        $siguiente = 1;

        if (is_string($mayor)) {
            $parte = substr($mayor, strlen((string) $anio) + 1);

            if (is_numeric($parte)) {
                $siguiente = (int) $parte + 1;
            }
        }

        return $anio . '-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}