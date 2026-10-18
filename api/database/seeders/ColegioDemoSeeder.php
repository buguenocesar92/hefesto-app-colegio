<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CanalComunicacion;
use App\Enums\CategoriaIncidente;
use App\Enums\EstadoAsistencia;
use App\Enums\EstadoComunicacion;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoMatricula;
use App\Enums\GravedadIncidente;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ApoderadoAlumno;
use App\Models\Asistencia;
use App\Models\Comunicacion;
use App\Models\ComunicacionDestinatario;
use App\Models\ComunicacionRecibo;
use App\Models\Consentimiento;
use App\Models\ContactoEmergencia;
use App\Models\Curso;
use App\Models\IncidenteConducta;
use App\Models\Nivel;
use App\Models\PlantillaComunicacion;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de desarrollo: DOS colegios de ejemplo.
 *
 * POR QUÉ DOS Y NO UNO
 * --------------------
 * Con un solo colegio no se puede comprobar NADA del aislamiento. El error
 * más grave de este tipo de sistema es que dos colegios se vean entre sí, y eso
 * solo se demuestra con dos colegios que existen al mismo tiempo. Las pruebas
 * de `tests/Feature/Aislamiento` crean los suyos, pero tener dos en la base de
 * desarrollo ayuda a comprobarlo a mano.
 *
 * NOTA SOBRE LA RLS
 * -----------------
 * Las tablas de negocio tienen Row Level Security, así que insertar filas
 * "a pelo" falla: `public.app_tenant_id()` no encuentra contexto y lanza
 * excepción. Por eso TODO lo de este seeder corre dentro de
 * `ContextoTenant::ejecutarAislado()`, que es la misma vía que usa la
 * aplicación de verdad. La tabla `colegios` sí se puede escribir directamente,
 * porque no lleva RLS.
 *
 * ADVERTENCIA
 * -----------
 * Los usuarios de ejemplo tienen `authentik_sub` ficticios: NO pueden entrar
 * al sistema hasta que sus identidades existan en Authentik con ese mismo
 * `sub`. Ver docs/04, sección "Datos de desarrollo".
 */
class ColegioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $contexto = new ContextoTenant();

        foreach ($this->colegiosDeEjemplo() as $datos) {
            $colegio = \App\Models\Colegio::create([
                'slug' => $datos['slug'],
                'nombre' => $datos['nombre'],
                'razon_social' => $datos['razon_social'],
                'rut' => $datos['rut'],
                'dominio_correo' => $datos['dominio'],
                'correo_contacto' => 'contacto@' . $datos['dominio'],
                'telefono_contacto' => '+56 2 2' . substr(hash('crc32b', $datos['slug']), 0, 3) . ' 0000',
                'activo' => true,
            ]);

            $contexto->ejecutarAislado($colegio->id, function () use ($colegio, $datos): void {
                $this->cargarColegio($colegio, $datos);
            });

            $this->command?->info(sprintf(
                '  Colegio "%s" creado con identificador "%s".',
                $colegio->nombre,
                $colegio->slug
            ));
        }
    }

    /**
     * @param  array<string, string>  $datos
     */
    private function cargarColegio(\App\Models\Colegio $colegio, array $datos): void
    {
        $tenantId = $colegio->id;
        $anio = (int) date('Y');

        // ---- Estructura académica ---------------------------------------
        $basica = Nivel::create([
            'tenant_id' => $tenantId,
            'nombre' => 'Básica',
            'orden' => 1,
            'activo' => true,
        ]);

        $media = Nivel::create([
            'tenant_id' => $tenantId,
            'nombre' => 'Media',
            'orden' => 2,
            'activo' => true,
        ]);

        $tercero = Curso::create([
            'tenant_id' => $tenantId,
            'nivel_id' => $basica->id,
            'nombre' => '3° Básico A',
            'sigla' => '3A',
            'orden' => 3,
            'activo' => true,
        ]);

        $cuarto = Curso::create([
            'tenant_id' => $tenantId,
            'nivel_id' => $basica->id,
            'nombre' => '4° Básico A',
            'sigla' => '4A',
            'orden' => 4,
            'activo' => true,
        ]);

        $segundo = Curso::create([
            'tenant_id' => $tenantId,
            'nivel_id' => $media->id,
            'nombre' => '2° Medio A',
            'sigla' => '2M',
            'orden' => 12,
            'activo' => true,
        ]);

        // ---- Profesionales ----------------------------------------------
        $profesoraJefa = Profesional::create([
            'tenant_id' => $tenantId,
            'nombre' => 'Carmen',
            'apellido' => 'Muñoz',
            'rut' => '12.345.678-9',
            'correo' => 'carmen.munoz@' . $datos['dominio'],
            'telefono' => '+56 9 8765 4321',
            'cargo' => 'Jefa de la Unidad Técnico Pedagógica',
            'es_docente' => true,
            'activo' => true,
        ]);

        $profesorHistoria = Profesional::create([
            'tenant_id' => $tenantId,
            'nombre' => 'Rodrigo',
            'apellido' => 'Pérez',
            'correo' => 'rodrigo.perez@' . $datos['dominio'],
            'telefono' => '+56 9 7654 3210',
            'cargo' => 'Docente de 3° y 4° Básico',
            'es_docente' => true,
            'activo' => true,
        ]);

        $orientadora = Profesional::create([
            'tenant_id' => $tenantId,
            'nombre' => 'Sofía',
            'apellido' => 'Ramírez',
            'correo' => 'sofia.ramirez@' . $datos['dominio'],
            'telefono' => '+56 9 6543 2109',
            'cargo' => 'Orientadora',
            'es_docente' => false,
            'activo' => true,
        ]);

        // De aquí sale el alcance de los docentes: los cursos donde tienen
        // carga, no su cargo.
        $profesorHistoria->cursos()->attach($tercero->id, ['tipo' => 'titular']);
        $profesorHistoria->cursos()->attach($cuarto->id, ['tipo' => 'titular']);
        $profesorHistoria->cursos()->attach($segundo->id, ['tipo' => 'practica']);
        $profesoraJefa->cursos()->attach($tercero->id, ['tipo' => 'titular']);
        $orientadora->cursos()->attach($tercero->id, ['tipo' => 'titular']);
        $orientadora->cursos()->attach($cuarto->id, ['tipo' => 'titular']);

        // ---- Usuarios ---------------------------------------------------
        $direccion = Usuario::create([
            'tenant_id' => $tenantId,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'direccion@' . $datos['dominio'],
            'nombre' => 'María',
            'apellido' => 'Fernández',
            'telefono' => '+56 9 1111 0000',
            'rol' => RolUsuario::Direccion,
            'profesional_id' => $profesoraJefa->id,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        $secretaria = Usuario::create([
            'tenant_id' => $tenantId,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'secretaria@' . $datos['dominio'],
            'nombre' => 'Andrea',
            'apellido' => 'Gutiérrez',
            'telefono' => '+56 2 2' . substr(hash('crc32b', $datos['slug'] . 's'), 0, 3) . ' 0000',
            'rol' => RolUsuario::Administracion,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        $docente = Usuario::create([
            'tenant_id' => $tenantId,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'docente@' . $datos['dominio'],
            'nombre' => 'Rodrigo',
            'apellido' => 'Pérez',
            'telefono' => '+56 9 7654 3210',
            'rol' => RolUsuario::Docente,
            'profesional_id' => $profesorHistoria->id,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        $comunicaciones = Usuario::create([
            'tenant_id' => $tenantId,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'comunicaciones@' . $datos['dominio'],
            'nombre' => 'Javiera',
            'apellido' => 'Contreras',
            'telefono' => '+56 9 5555 4444',
            'rol' => RolUsuario::Comunicaciones,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        $orientador = Usuario::create([
            'tenant_id' => $tenantId,
            'authentik_sub' => (string) Str::uuid(),
            'correo' => 'orientacion@' . $datos['dominio'],
            'nombre' => 'Sofía',
            'apellido' => 'Ramírez',
            'telefono' => '+56 9 6543 2109',
            'rol' => RolUsuario::Orientacion,
            'profesional_id' => $orientadora->id,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        // ---- Alumnos y familias ----------------------------------------
        $alumnos = $this->cargarAlumnos($tenantId, $anio, $tercero, $cuarto, $segundo);

        // ---- Plantillas -------------------------------------------------
        $this->cargarPlantillas($tenantId);

        // ---- Comunicaciones enviadas con sus acuses ----------------------
        $this->cargarComunicaciones($tenantId, $comunicaciones, $tercero, $basica, $alumnos);

        // ---- Asistencia y conducta (tablas separadas) -------------------
        $this->cargarRegistroDiario($tenantId, $docente, $orientador, $alumnos);

        // ---- Entrevista y acta ------------------------------------------
        $this->cargarAgenda($tenantId, $alumnos, $orientadora, $orientador);

        $this->command?->info(sprintf(
            '    6 usuarios, 3 cursos, %d alumnos, %d familias, comunicaciones con acuses, asistencia, '
            . 'conducta y agenda para "%s".',
            count($alumnos),
            $this->contarApoderados($tenantId),
            $datos['slug']
        ));
    }

    /**
     * Crea alumnos, familias, vínculos, contactos de emergencia,
     * matrículas y consentimientos.
     *
     * Se crean a mano y no con fábricas para que los datos de ejemplo sean
     * legibles y para que las referencias de matrícula tengan sentido.
     *
     * @return array<string, array<string, mixed>>
     */
    private function cargarAlumnos(string $tenantId, int $anio, Curso $tercero, Curso $cuarto, Curso $segundo): array
    {
        $plantilla = [
            // [nombre, apellido, curso, indice]
            ['Martina', 'Álvarez', $tercero, 1],
            ['Sebastián', 'Ríos', $tercero, 2],
            ['Isabella', 'Navarro', $tercero, 3],
            ['Matías', 'Castro', $tercero, 4],
            ['Camila', 'Espinoza', $tercero, 5],
            ['Benjamín', 'Vera', $cuarto, 6],
            ['Antonia', 'Rojas', $cuarto, 7],
            ['Lucas', 'Molina', $cuarto, 8],
            ['Florencia', 'Sepúlveda', $segundo, 9],
            ['Ignacio', 'Fuentes', $segundo, 10],
        ];

        $apellidosFamilia = [
            'Álvarez', 'Ríos', 'Navarro', 'Castro', 'Espinoza',
            'Vera', 'Rojas', 'Molina', 'Sepúlveda', 'Fuentes',
        ];

        $apoderados = [];

        foreach ($plantilla as [$nombre, $apellido, $curso, $indice]) {
            $alumno = Alumno::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'rut' => $this->rutDeEjemplo($indice),
                'fecha_nacimiento' => now()->subYears(rand(7, 15))->subMonths(rand(0, 11))->toDateString(),
                'sexo' => $indice % 2 === 0 ? 'masculino' : 'femenino',
                'direccion' => 'Av. Siempre Viva ' . rand(100, 900) . ', Santiago',
                'telefono' => '+56 9 8' . rand(100, 999) . ' ' . rand(1000, 9999),
                'fecha_ingreso' => $anio . '-03-05',
                'activo' => true,
            ]);

            $apoderado = Apoderado::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'apellido' => $apellidosFamilia[$indice - 1] ?? 'Familia',
                'correo' => 'familia.' . $nombre . '.' . strtolower($apellido) . '@ejemplo.cl',
                'telefono' => '+56 9 7' . rand(100, 999) . ' ' . rand(1000, 9999),
                'direccion' => 'Av. Siempre Viva ' . rand(100, 900) . ', Santiago',
                'activo' => true,
            ]);

            ApoderadoAlumno::create([
                'tenant_id' => $tenantId,
                'apoderado_id' => $apoderado->id,
                'alumno_id' => $alumno->id,
                'vinculo' => $indice % 2 === 0 ? 'madre' : 'padre',
                'es_responsable_legal' => true,
                'es_contacto_emergencia' => true,
            ]);

            // Una segunda familia, para que existan los casos de dos
            // apoderados y el alcance de cada uno se pueda comprobar.
            $segundoApoderado = Apoderado::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'apellido' => $apellidosFamilia[$indice - 1] ?? 'Familia',
                'correo' => 'contacto.' . $nombre . '.' . strtolower($apellido) . '@ejemplo.cl',
                'telefono' => '+56 9 6' . rand(100, 999) . ' ' . rand(1000, 9999),
                'activo' => true,
            ]);

            ApoderadoAlumno::create([
                'tenant_id' => $tenantId,
                'apoderado_id' => $segundoApoderado->id,
                'alumno_id' => $alumno->id,
                'vinculo' => $indice % 2 === 0 ? 'padre' : 'madre',
                'es_responsable_legal' => false,
            ]);

            ContactoEmergencia::create([
                'tenant_id' => $tenantId,
                'alumno_id' => $alumno->id,
                'nombre' => 'Abuela ' . $apellido,
                'parentesco' => 'Abuela',
                'telefono' => '+56 9 5' . rand(100, 999) . ' ' . rand(1000, 9999),
                'prioridad' => 2,
            ]);

            \App\Models\Matricula::create([
                'tenant_id' => $tenantId,
                'alumno_id' => $alumno->id,
                'curso_id' => $curso->id,
                'anio_escolar' => $anio,
                'numero_matricula' => $anio . '-' . str_pad((string) $indice, 4, '0', STR_PAD_LEFT),
                'estado' => EstadoMatricula::Matriculado,
                'fecha_matricula' => $anio . '-03-05',
            ]);

            // Consentimiento de la familia. Sin este registro, el colegio no
            // podría probar que la familia autorizó el tratamiento de datos
            // de su hijo. Ver docs/07.
            Consentimiento::create([
                'tenant_id' => $tenantId,
                'apoderado_id' => $apoderado->id,
                'alumno_id' => $alumno->id,
                'alcance' => 'todos',
                'version_documento' => 'v1-2026',
                'otorgado_por' => $apoderado->nombre . ' ' . $apoderado->apellido,
                'fecha_otorgamiento' => $anio . '-03-10',
            ]);

            $apoderados[] = $apoderado;
            $apoderados[] = $segundoApoderado;
        }

        // Hay dos apoderados por alumno; el mapa deja la familia principal de
        // cada alumno en una posición estable, para poder referenciarla por
        // número en las comunicaciones de ejemplo.
        $mapa = [];

        foreach ($apoderados as $posicion => $apoderado) {
            $mapa[(int) ceil($posicion / 2)] = $apoderado;
        }

        return ['apoderados' => $mapa];
    }

    private function cargarPlantillas(string $tenantId): void
    {
        $plantillas = [
            [
                'nombre' => 'Reunión de apoderados',
                'canal' => CanalComunicacion::Correo,
                'asunto' => 'Invitación a reunión de apoderados',
                'cuerpo' => "Estimadas familias:\n\nSe extiende la invitación a la reunión de apoderados del curso.\n\n"
                    . "Fecha: {{fecha}}\nHora: {{hora}}\nLugar: {{lugar}}\n\n"
                    . 'La asistencia de los padres es fundamental para el buen desarrollo de nuestros alumnos.\n\n'
                    . 'Atentamente,\nLa dirección',
                'requiere_acuse' => true,
            ],
            [
                'nombre' => 'Cambio de horario',
                'canal' => CanalComunicacion::Whatsapp,
                'asunto' => 'Cambio de horario',
                'cuerpo' => 'Estimadas familias: por motivos de organización, se informa un cambio de horario que '
                    . 'se avisará con antelación. Gracias por su atención.',
                'requiere_acuse' => false,
            ],
            [
                'nombre' => 'Entrega de documentos',
                'canal' => CanalComunicacion::Presencial,
                'asunto' => 'Entrega de documentos',
                'cuerpo' => 'Se informa que los documentos de {{alumno}} están disponibles en secretaría para su '
                    . 'recepción en horario de atención.',
                'requiere_acuse' => true,
            ],
        ];

        foreach ($plantillas as $plantilla) {
            PlantillaComunicacion::create([
                'tenant_id' => $tenantId,
                'nombre' => $plantilla['nombre'],
                'canal' => $plantilla['canal'],
                'asunto' => $plantilla['asunto'],
                'cuerpo' => $plantilla['cuerpo'],
                'requiere_acuse' => $plantilla['requiere_acuse'],
                'activa' => true,
            ]);
        }
    }

    /**
     * Comunicaciones ENVIADAS, con destinatarios y acuses.
     *
     * Esta es la parte que muestra para qué existe el sistema: avisos que se
     * enviaron, a qué familias, y cuáles confirmaron recepción. Sin esto la
     * pantalla de acuses sale vacía y no hay nada que comprobar.
     *
     * @param  array<string, array<string, mixed>>  $alumnos
     */
    private function cargarComunicaciones(
        string $tenantId,
        Usuario $remitente,
        Curso $tercero,
        Nivel $basica,
        array $alumnos,
    ): void {
        $comunicaciones = [
            [
                'titulo' => 'Reunión de apoderados de 3° Básico',
                'cuerpo' => "Estimadas familias:\n\nSe extiende la invitación a la reunión de apoderados del 3° Básico A.\n\n"
                    . "Fecha: jueves 12, 18:30 horas.\nLugar: sala de reuniones.\n\n"
                    . 'En la reunión se Informará el estado del curso y el calendario de evaluaciones.\n\n'
                    . 'Les rogamos confirmar recepción de este aviso.\n\nAtentamente,\nJefatura UTP',
                'canal' => CanalComunicacion::Correo,
                'tipo_destinatario' => 'curso',
                'curso_id' => $tercero->id,
                'requiere_acuse' => true,
                'dias' => 14,
                // Las tres primeras familias confirmaron; el resto, no. Un
                // aviso con acuses parciales es el caso normal y el que hay
                // que ver funcionando.
                'con_acuse' => 3,
            ],
            [
                'titulo' => 'Cambio de horario de educación física',
                'cuerpo' => 'Estimadas familias: durante esta semana, las clases de educación física '
                    . 'de 3° Básico se movieron al primer bloque. Se avisa para que organicen la mañana.',
                'canal' => CanalComunicacion::Whatsapp,
                'tipo_destinatario' => 'curso',
                'curso_id' => $tercero->id,
                'requiere_acuse' => false,
                'dias' => 7,
                'con_acuse' => 0,
            ],
            [
                'titulo' => 'Calendario de evaluaciones del nivel',
                'cuerpo' => 'Se comparte el calendario de evaluaciones del nivel Básico. Se ruega revisar las '
                    . 'fechas y tomar conocimiento.',
                'canal' => CanalComunicacion::Correo,
                'tipo_destinatario' => 'nivel',
                'curso_id' => null,
                'nivel_id' => $basica->id,
                'requiere_acuse' => true,
                'dias' => 3,
                'con_acuse' => 5,
            ],
            [
                'titulo' => 'Suspensión de clases por lluvia',
                'cuerpo' => 'Por condiciones meteorológicas, las clases se suspenderán mañana. '
                    . 'Se avisará por este medio cuando se reanuden.',
                'canal' => CanalComunicacion::Whatsapp,
                'tipo_destinatario' => 'nivel',
                'curso_id' => null,
                'nivel_id' => $basica->id,
                'requiere_acuse' => false,
                'dias' => 1,
                'con_acuse' => 0,
            ],
            [
                'titulo' => 'Aviso de entrevista familiar',
                'cuerpo' => 'Nos contactaremos para coordinar una entrevista con orientación. '
                    . 'Le contactaremos por teléfono para agendar.',
                'canal' => CanalComunicacion::Telefono,
                'tipo_destinatario' => 'alumno',
                'curso_id' => null,
                'nivel_id' => null,
                'requiere_acuse' => true,
                'dias' => 2,
                'con_acuse' => 1,
                'soloAlumno' => 1,
            ],
        ];

        // El aviso dirigido a un alumno concreto guarda el alumno_id, igual
        // que lo haría el endpoint. Es lo que permite que la lista de
        // destinatarios se resuelva después sin depender de un curso.
        $primerAlumno = \App\Models\Matricula::query()
            ->where('tenant_id', $tenantId)
            ->value('alumno_id');

        foreach ($comunicaciones as $indice => $datos) {
            $comunicacion = Comunicacion::create([
                'tenant_id' => $tenantId,
                'referencia' => sprintf('COM-%d-%06d', (int) date('Y'), $indice + 1),
                'titulo' => $datos['titulo'],
                'cuerpo' => $datos['cuerpo'],
                'canal' => $datos['canal'],
                'tipo_destinatario' => $datos['tipo_destinatario'],
                'curso_id' => $datos['curso_id'] ?? null,
                'nivel_id' => $datos['nivel_id'] ?? null,
                'alumno_id' => $datos['tipo_destinatario'] === 'alumno' ? $primerAlumno : null,
                'apoderado_id' => null,
                'remitente_usuario_id' => $remitente->id,
                'requiere_acuse' => $datos['requiere_acuse'],
                'estado' => EstadoComunicacion::Enviada,
                'enviada_en' => now()->subDays($datos['dias']),
            ]);

            $destinatarios = $this->destinatariosDeEjemplo($datos, $alumnos);

            foreach ($destinatarios as $posicion => $apoderado) {
                ComunicacionDestinatario::create([
                    'tenant_id' => $tenantId,
                    'comunicacion_id' => $comunicacion->id,
                    'apoderado_id' => $apoderado->id,
                ]);

                // Solo las primeras familias confirmaron. El resto queda
                // pendiente, que es lo que la pantalla de acuses tiene que
                // mostrar bien.
                if ($posicion < $datos['con_acuse']) {
                    ComunicacionRecibo::create([
                        'tenant_id' => $tenantId,
                        'comunicacion_id' => $comunicacion->id,
                        'apoderado_id' => $apoderado->id,
                        'recibido_en' => $comunicacion->enviada_en->copy()->addHours(rand(1, 20)),
                        'medio' => $datos['canal'] === CanalComunicacion::Correo ? 'web' : $datos['canal']->value,
                        'direccion_ip' => null,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<string, mixed>>  $alumnos
     * @return list<\App\Models\Apoderado>
     */
    private function destinatariosDeEjemplo(array $datos, array $alumnos): array
    {
        $familias = [];

        if (isset($datos['soloAlumno'])) {
            $familia = $alumnos['apoderados'][$datos['soloAlumno']] ?? null;

            return $familia !== null ? [$familia] : [];
        }

        foreach ($alumnos['apoderados'] as $apoderado) {
            $familias[] = $apoderado;
        }

        return $familias;
    }

    /**
     * Asistencia y conducta, en tablas separadas.
     *
     * @param  array<string, array<string, mixed>>  $alumnos
     */
    private function cargarRegistroDiario(
        string $tenantId,
        Usuario $docente,
        Usuario $orientador,
        array $alumnos,
    ): void {
        $cursos = Curso::query()->where('tenant_id', $tenantId)->get();
        $estados = [
            EstadoAsistencia::Presente,
            EstadoAsistencia::Presente,
            EstadoAsistencia::Presente,
            EstadoAsistencia::Ausente,
            EstadoAsistencia::Tarde,
            EstadoAsistencia::Justificado,
        ];

        // Últimos cinco días hábiles, hacia atrás.
        for ($dia = 1; $dia <= 5; $dia++) {
            $fecha = now()->subDays($dia);

            if ($fecha->isWeekend()) {
                continue;
            }

            foreach ($cursos as $curso) {
                $matriculados = \App\Models\Matricula::query()
                    ->where('curso_id', $curso->id)
                    ->where('estado', EstadoMatricula::Matriculado->value)
                    ->get();

                foreach ($matriculados as $posicion => $matricula) {
                    $estado = $estados[($dia + $posicion) % count($estados)];

                    Asistencia::create([
                        'tenant_id' => $tenantId,
                        'alumno_id' => $matricula->alumno_id,
                        'curso_id' => $curso->id,
                        'fecha' => $fecha->toDateString(),
                        'estado' => $estado,
                        'justificacion' => $estado === EstadoAsistencia::Justificado
                            ? 'Licencia médica entregada en secretaría.'
                            : null,
                        'registrado_por_usuario_id' => $docente->id,
                        'registrado_en' => $fecha->copy()->setTime(8, 30),
                    ]);
                }
            }
        }

        // Incidentes de conducta. Deliberadamente pocos y de gravedad
        // distinta, para que se vea que la columna `gravedad` manda en la
        // decisión de comunicar.
        $primerAlumno = \App\Models\Matricula::query()->where('tenant_id', $tenantId)->first();

        if ($primerAlumno !== null) {
            IncidenteConducta::create([
                'tenant_id' => $tenantId,
                'alumno_id' => $primerAlumno->alumno_id,
                'curso_id' => $primerAlumno->curso_id,
                'fecha' => now()->subDays(9)->toDateString(),
                'categoria' => CategoriaIncidente::Convivencia->value,
                'gravedad' => GravedadIncidente::Leve->value,
                'descripcion' => 'Interrumpió la clase en dos ocasiones. Se conversó en el momento y no '
                    . 'se repitió.',
                'medida' => 'Conversación privada con el alumno.',
                'registrado_por_usuario_id' => $docente->id,
                'registrado_en' => now()->subDays(9),
                'notificado_apoderado' => false,
            ]);

            $segundo = \App\Models\Matricula::query()
                ->where('tenant_id', $tenantId)
                ->where('alumno_id', '!=', $primerAlumno->alumno_id)
                ->first();

            if ($segundo !== null) {
                IncidenteConducta::create([
                    'tenant_id' => $tenantId,
                    'alumno_id' => $segundo->alumno_id,
                    'curso_id' => $segundo->curso_id,
                    'fecha' => now()->subDays(4)->toDateString(),
                    'categoria' => CategoriaIncidente::Convivencia->value,
                    'gravedad' => GravedadIncidente::Media->value,
                    'descripcion' => 'Participó en una discusión con un compañero durante el recreo, '
                        . 'con empujones. Se separó la situación y se conversó con ambos.',
                    'medida' => 'Se cita a la familia para entrevista. Se avisa por comunicación.',
                    'registrado_por_usuario_id' => $orientador->id,
                    'registrado_en' => now()->subDays(4),
                    'notificado_apoderado' => false,
                ]);
            }
        }
    }

    /**
     * Entrevista agendada y acta de una reunión.
     *
     * @param  array<string, array<string, mixed>>  $alumnos
     */
    private function cargarAgenda(
        string $tenantId,
        array $alumnos,
        Profesional $orientadora,
        Usuario $orientador,
    ): void {
        $primeraMatricula = \App\Models\Matricula::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($primeraMatricula === null) {
            return;
        }

        $entrevista = \App\Models\Entrevista::create([
            'tenant_id' => $tenantId,
            'alumno_id' => $primeraMatricula->alumno_id,
            'apoderado_id' => $alumnos['apoderados'][1] ?? null,
            'profesional_id' => $orientadora->id,
            'tipo' => 'entrevista_familiar',
            'fecha' => now()->addDays(5)->toDateString(),
            'hora' => '16:30',
            'lugar' => 'Sala de orientación',
            'estado' => EstadoEntrevista::Agendada,
            'motivo' => 'Entrevista solicitada por orientación para revisar el proceso socioemocional del alumno '
                . 'y acordar apoyos con la familia.',
        ]);

        \App\Models\ActaReunion::create([
            'tenant_id' => $tenantId,
            'entrevista_id' => $entrevista->id,
            'autor_usuario_id' => $orientador->id,
            'titulo' => 'Reunión de curso:Calendario de evaluaciones',
            'resumen' => 'Se informó el calendario de evaluaciones del nivel y se respondieron preguntas sobre '
                . 'los período de receso. La comunicación del calendario se hizo por correo con acuse de recibo.',
            'acuerdos' => '1. Publicar el calendario en el portal antes del 20 de este mes.\n'
                . '2. Horario de atención a familias los jueves de 16:00 a 17:30.\n'
                . '3. Las familias que no confirmen recepción del aviso se contactarán por teléfono.',
            'fecha' => now()->subDays(20)->toDateString(),
        ]);
    }

    private function contarApoderados(string $tenantId): int
    {
        return Apoderado::query()->where('tenant_id', $tenantId)->count();
    }

    /**
     * RUT de ejemplo, con dígito verificador correcto.
     */
    private function rutDeEjemplo(int $semilla): string
    {
        $cuerpo = str_pad((string) (10_000_000 + $semilla * 137), 8, '0', STR_PAD_LEFT);

        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += ((int) $cuerpo[$i]) * (2 + ($i % 7));
        }

        $resto = 11 - ($suma % 11);

        $dv = match ($resto) {
            11 => 0,
            10 => 'K',
            default => $resto,
        };

        return substr($cuerpo, 0, 2) . '.' . substr($cuerpo, 2, 3) . '.' . substr($cuerpo, 5, 3) . '-' . $dv;
    }

    /**
     * @return list<array<string, string>>
     */
    private function colegiosDeEjemplo(): array
    {
        return [
            [
                'slug' => 'colegio-los-alamos',
                'nombre' => 'Colegio Los Álamos',
                'razon_social' => 'Colegio Los Álamos SpA',
                'rut' => '76.111.111-1',
                'dominio' => 'losalamos.cl',
            ],
            [
                'slug' => 'colegio-san-antonio',
                'nombre' => 'Colegio San Antonio',
                'razon_social' => 'Colegio San Antonio Ltda.',
                'rut' => '77.222.222-2',
                'dominio' => 'sanantonio.cl',
            ],
        ];
    }
}