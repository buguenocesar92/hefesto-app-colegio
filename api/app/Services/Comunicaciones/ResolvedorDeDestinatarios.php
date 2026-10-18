<?php

declare(strict_types=1);

namespace App\Services\Comunicaciones;

use App\Enums\TipoDestinatario;
use App\Models\Comunicacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve a qué familias va una comunicación y deja constancia.
 *
 * ESTE ES EL CORAZÓN DEL MÓDULO
 * ----------------------------
 * El colegio no elige "a quién" comunicarle persona por persona: elige un
 * curso, y el sistema resuelve la lista. Eso tiene una consecuencia que es
 * el motivo de que este módulo exista: la lista queda ESCRITA en la base.
 *
 * Si en marzo se cambió la matrícula del hijo de una familia, y en junio se
 * manda un aviso al curso, la familia que hay que avisar es la de marzo. Y
 * si en septiembre preguntan "¿nos avisaron esto?", la respuesta sale de
 * `comunicacion_destinatarios`, no de recalcular a quién le correspondía.
 *
 * LO QUE ESTE SERVICIO NO HACE
 * ---------------------------
 * No envía nada. Registra lo que el colegio ya mandó. Ver docs/09.
 */
class ResolvedorDeDestinatarios
{
    /**
     * Devuelve los identificadores de apoderado destinatarios.
     *
     * Cada tipo de destinatario usa un parámetro distinto. Solo se mira el
     * que corresponde al tipo, y el FormRequest ya exige que el resto venga
     * en nulo.
     *
     * @return list<string>
     */
    public function resolver(
        TipoDestinatario $tipo,
        ?string $cursoId = null,
        ?string $nivelId = null,
        ?string $alumnoId = null,
        ?string $apoderadoId = null,
    ): array {
        return match ($tipo) {
            TipoDestinatario::Curso => $this->apoderadosDelCurso($cursoId),
            TipoDestinatario::Nivel => $this->apoderadosDelNivel($nivelId),
            TipoDestinatario::Apoderado => $apoderadoId !== null ? [$apoderadoId] : [],
            TipoDestinatario::Alumno => $this->apoderadosDelAlumno($alumnoId),
        };
    }

    /**
     * Todas las familias con al menos un hijo matriculado en el curso.
     *
     * Solo matrícula `matriculado`: un alumno retirado del año anterior no
     * debe seguir recibiendo los avisos del curso.
     *
     * @return list<string>
     */
    private function apoderadosDelCurso(?string $cursoId): array
    {
        if ($cursoId === null) {
            return [];
        }

        return $this->ids(
            DB::table('matriculas as m')
                ->join('apoderado_alumnos as aa', 'aa.alumno_id', '=', 'm.alumno_id')
                ->where('m.curso_id', $cursoId)
                ->where('m.estado', 'matriculado')
                ->select('aa.apoderado_id')
        );
    }

    /**
     * @return list<string>
     */
    private function apoderadosDelNivel(?string $nivelId): array
    {
        if ($nivelId === null) {
            return [];
        }

        return $this->ids(
            DB::table('cursos as c')
                ->join('matriculas as m', 'm.curso_id', '=', 'c.id')
                ->join('apoderado_alumnos as aa', 'aa.alumno_id', '=', 'm.alumno_id')
                ->where('c.nivel_id', $nivelId)
                ->where('m.estado', 'matriculado')
                ->select('aa.apoderado_id')
        );
    }

    /**
     * @return list<string>
     */
    private function apoderadosDelAlumno(?string $alumnoId): array
    {
        if ($alumnoId === null) {
            return [];
        }

        return $this->ids(
            DB::table('apoderado_alumnos')
                ->where('alumno_id', $alumnoId)
                ->select('apoderado_id')
        );
    }

    /**
     * Normaliza a lista de identificadores únicos.
     *
     * Una familia con dos hijos en el mismo curso recibe UN aviso, no dos.
     * Y `distinct` importa: la misma familia puede aparecer por dos alumnos.
     *
     * @param  \Illuminate\Database\Query\Builder  $consulta
     * @return list<string>
     */
    private function ids($consulta): array
    {
        return $consulta
            ->distinct()
            ->pluck('apoderado_id')
            ->map(static fn ($valor): string => (string) $valor)
            ->values()
            ->all();
    }

    /**
     * Escribe los destinatarios de una comunicación.
     *
     * @param  list<string>  $apoderadoIds
     * @return int  Cuántos destinatarios se registraron.
     */
    public function registrar(Comunicacion $comunicacion, array $apoderadoIds): int
    {
        if ($apoderadoIds === []) {
            return 0;
        }

        $filas = array_map(static fn (string $apoderadoId): array => [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $comunicacion->tenant_id,
            'comunicacion_id' => $comunicacion->id,
            'apoderado_id' => $apoderadoId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $apoderadoIds);

        DB::table('comunicacion_destinatarios')->insert($filas);

        return count($filas);
    }
}