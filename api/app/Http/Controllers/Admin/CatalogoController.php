<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ApoderadoAlumno;
use App\Models\Consentimiento;
use App\Models\Curso;
use App\Models\Nivel;
use App\Models\PlantillaComunicacion;
use App\Models\Profesional;
use App\Http\Resources\ApoderadoResource;
use App\Http\Resources\ConsentimientoResource;
use App\Http\Resources\CursoResource;
use App\Http\Resources\NivelResource;
use App\Http\Resources\PlantillaResource;
use App\Http\Resources\ProfesionalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administración del colegio: niveles, cursos, profesionales, plantillas,
 * apoderados y consentimientos.
 *
 * TODO PASA POR AQUÍ
 * ------------------
 * Los catálogos los administra dirección o administración, y para eso sirve
 * `GestionAdministrativaPolicy`.
 *
 * Los dos casos que tienen más criterio que el resto, y que por eso están
 * en este controlador y no en uno genérico:
 *
 *  - APODERADOS Y VÍNCULOS: crear la ficha de una familia y decir con qué
 *    hijos está vinculada es lo que hace que el padre vea a su hijo y no a
 *    otros. Es un acto con efecto de acceso, no un CRUD.
 *  - CONSENTIMIENTOS: registrar que una familia autorizó el tratamiento de
 *    datos. Ver docs/07.
 */
class CatalogoController extends Controller
{
    // ------------------------------------------------------------------
    // Niveles
    // ------------------------------------------------------------------

    public function niveles(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Nivel::class);

        return NivelResource::collection(
            Nivel::query()->orderBy('orden')->orderBy('nombre')->get()
        );
    }

    public function guardarNivel(Request $request): JsonResponse
    {
        $this->authorize('crear', Nivel::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nombre.required' => 'Indique el nombre del nivel.',
        ]);

        $nivel = Nivel::create([
            'tenant_id' => $request->user()->tenant_id,
            'nombre' => $datos['nombre'],
            'orden' => $datos['orden'] ?? 0,
            'activo' => true,
        ]);

        return response()->json([
            'datos' => NivelResource::make($nivel)->resolve($request),
            'mensaje' => 'Nivel creado.',
        ], 201);
    }

    // ------------------------------------------------------------------
    // Cursos
    // ------------------------------------------------------------------

    public function cursos(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Curso::class);

        $consulta = Curso::query()->with('nivel')->orderBy('orden')->orderBy('nombre');

        if ($nivelId = $request->query('nivel_id')) {
            $consulta->where('nivel_id', $nivelId);
        }

        return CursoResource::collection($consulta->get());
    }

    public function guardarCurso(Request $request): JsonResponse
    {
        $this->authorize('crear', Curso::class);

        $datos = $request->validate([
            'nivel_id' => ['required', 'uuid'],
            'nombre' => ['required', 'string', 'max:120'],
            'sigla' => ['required', 'string', 'max:20'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nivel_id.required' => 'Indique el nivel del curso.',
            'nombre.required' => 'Indique el nombre del curso.',
            'sigla.required' => 'Indique la sigla del curso (por ejemplo, 3B).',
        ]);

        $sigla = mb_strtoupper(trim($datos['sigla']));

        if (Curso::query()->where('sigla', $sigla)->exists()) {
            throw ValidationException::withMessages([
                'sigla' => sprintf('La sigla "%s" ya está en uso en este colegio.', $sigla),
            ]);
        }

        $curso = Curso::create([
            'tenant_id' => $request->user()->tenant_id,
            'nivel_id' => $datos['nivel_id'],
            'nombre' => $datos['nombre'],
            'sigla' => $sigla,
            'orden' => $datos['orden'] ?? 0,
            'activo' => true,
        ]);

        return response()->json([
            'datos' => CursoResource::make($curso->load('nivel'))->resolve($request),
            'mensaje' => 'Curso creado.',
        ], 201);
    }

    // ------------------------------------------------------------------
    // Profesionales
    // ------------------------------------------------------------------

    public function profesionales(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Profesional::class);

        return ProfesionalResource::collection(
            Profesional::query()->orderBy('nombre')->get()
        );
    }

    public function guardarProfesional(Request $request): JsonResponse
    {
        $this->authorize('crear', Profesional::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'rut' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'cargo' => ['required', 'string', 'max:120'],
            'es_docente' => ['sometimes', 'boolean'],
        ], [
            'nombre.required' => 'Indique el nombre.',
            'apellido.required' => 'Indique el apellido.',
            'cargo.required' => 'Indique el cargo.',
            'correo.email' => 'El correo no tiene un formato válido.',
        ]);

        $profesional = Profesional::create([
            'tenant_id' => $request->user()->tenant_id,
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'rut' => $datos['rut'] ?? null,
            'correo' => $datos['correo'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'cargo' => $datos['cargo'],
            'es_docente' => (bool) ($datos['es_docente'] ?? false),
            'activo' => true,
        ]);

        return response()->json([
            'datos' => ProfesionalResource::make($profesional)->resolve($request),
            'mensaje' => 'Profesional creado.',
        ], 201);
    }

    // ------------------------------------------------------------------
    // Plantillas de comunicación
    // ------------------------------------------------------------------

    public function plantillas(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', PlantillaComunicacion::class);

        return PlantillaResource::collection(
            PlantillaComunicacion::query()->orderBy('nombre')->get()
        );
    }

    public function guardarPlantilla(Request $request): JsonResponse
    {
        $this->authorize('crear', PlantillaComunicacion::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'canal' => ['required', 'string', 'in:correo,whatsapp,presencial,telefono,web'],
            'asunto' => ['required', 'string', 'max:180'],
            'cuerpo' => ['required', 'string', 'max:20000'],
            'requiere_acuse' => ['sometimes', 'boolean'],
        ], [
            'nombre.required' => 'Indique el nombre de la plantilla.',
            'canal.required' => 'Indique el canal.',
            'asunto.required' => 'Indique el asunto.',
            'cuerpo.required' => 'Escriba el texto de la plantilla.',
        ]);

        $plantilla = PlantillaComunicacion::create([
            'tenant_id' => $request->user()->tenant_id,
            'nombre' => $datos['nombre'],
            'canal' => $datos['canal'],
            'asunto' => $datos['asunto'],
            'cuerpo' => $datos['cuerpo'],
            'requiere_acuse' => (bool) ($datos['requiere_acuse'] ?? false),
            'activa' => true,
        ]);

        return response()->json([
            'datos' => PlantillaResource::make($plantilla)->resolve($request),
            'mensaje' => 'Plantilla creada.',
        ], 201);
    }

    // ------------------------------------------------------------------
    // Apoderados y vínculos
    // ------------------------------------------------------------------

    public function apoderados(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Apoderado::class);

        $consulta = Apoderado::query()->withCount('alumnos')->orderBy('apellido');

        $busqueda = trim((string) $request->query('q', ''));

        if ($busqueda !== '') {
            $consulta->where(function ($interna) use ($busqueda): void {
                $interna->where('nombre', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('apellido', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('correo', 'ilike', '%' . $busqueda . '%')
                    ->orWhere('rut', 'ilike', '%' . $busqueda . '%');
            });
        }

        return ApoderadoResource::collection($consulta->paginate(25)->withQueryString());
    }

    /**
     * Crea la ficha de una familia y la vincula con sus hijos.
     *
     * El vínculo es lo que abre el acceso del padre a su hijo, así que se
     * escribe en la misma operación: una familia creada sin hijos es un
     * estado incompleto que alguien tendrá que corregir después.
     */
    public function guardarApoderado(Request $request): JsonResponse
    {
        $this->authorize('crear', Apoderado::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'rut' => ['nullable', 'string', 'max:20'],
            'correo' => ['required', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:240'],

            'vinculos' => ['nullable', 'array'],
            'vinculos.*.alumno_id' => ['required', 'uuid'],
            'vinculos.*.vinculo' => ['required', 'string', 'max:40'],
            'vinculos.*.es_responsable_legal' => ['sometimes', 'boolean'],
        ], [
            'nombre.required' => 'Indique el nombre.',
            'apellido.required' => 'Indique el apellido.',
            'correo.required' => 'Indique el correo. Es con el que la familia entra al portal.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'vinculos.*.alumno_id.required' => 'Cada vínculo necesita el alumno.',
            'vinculos.*.vinculo.required' => 'Cada vínculo necesita el parentesco.',
        ]);

        if (Apoderado::query()->where('correo', mb_strtolower($datos['correo']))->exists()) {
            throw ValidationException::withMessages([
                'correo' => 'Ya existe una ficha de familia con ese correo en este colegio.',
            ]);
        }

        $tenantId = $request->user()->tenant_id;

        $apoderado = Apoderado::create([
            'tenant_id' => $tenantId,
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'rut' => $datos['rut'] ?? null,
            'correo' => mb_strtolower($datos['correo']),
            'telefono' => $datos['telefono'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'activo' => true,
        ]);

        foreach ((array) ($datos['vinculos'] ?? []) as $vinculo) {
            ApoderadoAlumno::create([
                'tenant_id' => $tenantId,
                'apoderado_id' => $apoderado->id,
                'alumno_id' => $vinculo['alumno_id'],
                'vinculo' => $vinculo['vinculo'],
                'es_responsable_legal' => (bool) ($vinculo['es_responsable_legal'] ?? false),
            ]);
        }

        return response()->json([
            'datos' => ApoderadoResource::make($apoderado->load('alumnos'))->resolve($request),
            'mensaje' => 'Ficha de familia creada.',
        ], 201);
    }

    // ------------------------------------------------------------------
    // Consentimientos
    // ------------------------------------------------------------------

    public function consentimientos(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ver', Consentimiento::class);

        return ConsentimientoResource::collection(
            Consentimiento::query()
                ->with(['apoderado', 'alumno'])
                ->orderByDesc('fecha_otorgamiento')
                ->paginate(25)
                ->withQueryString()
        );
    }

    /**
     * Registra un consentimiento.
     *
     * Se guarda la evidencia aunque esté vacío. Un consentimiento registrado
     * sin documento es mejor que ninguno: deja constancia de que se pidió.
     */
    public function guardarConsentimiento(Request $request): JsonResponse
    {
        $this->authorize('registrarConsentimiento', Consentimiento::class);

        $datos = $request->validate([
            'apoderado_id' => ['required', 'uuid'],
            'alumno_id' => ['nullable', 'uuid'],
            'alcance' => ['required', 'string', 'in:matricula,comunicaciones,datos_sensibles,todos'],
            'version_documento' => ['required', 'string', 'max:40'],
            'otorgado_por' => ['required', 'string', 'max:160'],
            'fecha_otorgamiento' => ['required', 'date', 'before_or_equal:today'],
            'evidencia_ruta' => ['nullable', 'string', 'max:300'],
        ], [
            'apoderado_id.required' => 'Indique la familia.',
            'alcance.required' => 'Indique el alcance del consentimiento.',
            'version_documento.required' => 'Indique la versión del documento firmado. Sin esto no se puede probar qué firmó la familia.',
            'otorgado_por.required' => 'Indique quién firmó.',
            'fecha_otorgamiento.required' => 'Indique la fecha.',
            'fecha_otorgamiento.before_or_equal' => 'La fecha no puede ser futura.',
        ]);

        $consentimiento = Consentimiento::create([
            'tenant_id' => $request->user()->tenant_id,
            'apoderado_id' => $datos['apoderado_id'],
            'alumno_id' => $datos['alumno_id'] ?? null,
            'alcance' => $datos['alcance'],
            'version_documento' => $datos['version_documento'],
            'otorgado_por' => $datos['otorgado_por'],
            'fecha_otorgamiento' => $datos['fecha_otorgamiento'],
            'evidencia_ruta' => $datos['evidencia_ruta'] ?? null,
        ]);

        return response()->json([
            'datos' => ConsentimientoResource::make($consentimiento->load(['apoderado', 'alumno']))->resolve($request),
            'mensaje' => 'Consentimiento registrado.',
        ], 201);
    }

    /**
     * Revoca un consentimiento.
     *
     * No se borra: queda la fila con `revocado_en`. Un registro que se
     * borra al revocarse no sirve para demostrar que hubo un período en el
     * que el tratamiento estaba autorizado.
     */
    public function revocarConsentimiento(Request $request, Consentimiento $consentimiento): JsonResponse
    {
        $this->authorize('registrarConsentimiento', $consentimiento);

        $consentimiento->forceFill(['revocado_en' => now()])->save();

        return response()->json([
            'datos' => ConsentimientoResource::make($consentimiento->fresh()->load(['apoderado', 'alumno']))->resolve($request),
            'mensaje' => 'Consentimiento revocado. El registro se conserva.',
        ]);
    }
}