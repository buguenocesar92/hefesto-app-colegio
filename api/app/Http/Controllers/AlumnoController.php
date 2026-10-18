<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AlumnoResource;
use App\Models\Alumno;
use App\Services\Alumnos\AlcanceDeAlumnos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Alumnos: ficha y listado.
 *
 * LA REGLA DE ESTE CONTROLADOR
 * ---------------------------
 * Ninguna consulta de alumnos se escribe acá. Todas salen de
 * `AlcanceDeAlumnos::apoderablesPor()`, que ya trae aplicado el filtro de
 * la persona.
 *
 * Es la diferencia entre un sistema donde el padre ve a sus hijos y otro
 * donde el padre ve también los hijos del vecino. No es una línea de código:
 * es que no exista otro camino.
 */
class AlumnoController extends Controller
{
    public function __construct(private readonly AlcanceDeAlumnos $alcance) {}

    /**
     * Listado de alumnos que la persona puede ver.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('verListado', Alumno::class);

        $usuario = $request->user();

        $consulta = $this->alcance->apoderablesPor($usuario)
            ->with(['matricula.curso']);

        if ($curso = $request->query('curso_id')) {
            $consulta->whereHas('matriculas', function ($interna) use ($curso): void {
                $interna->where('curso_id', $curso);
            });
        }

        if ($estado = $request->query('estado')) {
            $consulta->whereHas('matriculas', function ($interna) use ($estado): void {
                $interna->where('estado', $estado);
            });
        }

        $busqueda = trim((string) $request->query('q', ''));

        if ($busqueda !== '') {
            $consulta->where(function ($interna) use ($busqueda): void {
                $interna->where('nombre', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('apellido', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('rut', 'ilike', '%' . $busqueda . '%');
            });
        }

        $pagina = $consulta->paginate($this->tamanoDePagina($request))->withQueryString();

        // El listado NO trae la ficha completa: ni dirección, ni contactos
        // de emergencia, ni apoderados. Solo lo necesario para elegir a
        // alguien. La ficha completa es otra operación.
        return AlumnoResource::collection($pagina);
    }

    /**
     * Ficha completa de un alumno.
     *
     * Aquí sí se exige `verFichaCompleta`, que es más restrictivo que
     * `ver`: ver que un alumno existe no autoriza a abrir su ficha.
     */
    public function show(Request $request, Alumno $alumno): AlumnoResource
    {
        $this->authorize('verFichaCompleta', $alumno);

        $alumno->load(['matricula.curso', 'contactosEmergencia', 'apoderados']);

        return AlumnoResource::make($alumno);
    }

    private function tamanoDePagina(Request $request): int
    {
        $pedido = (int) $request->query('per_page', 15);

        return max(1, min($pedido, 50));
    }
}