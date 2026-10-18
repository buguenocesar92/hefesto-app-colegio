<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\Auth\DescubridorOidc;
use App\Services\Auth\ResolvedorDeCuenta;
use App\Services\Auth\VerificadorIdToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Acceso por OIDC contra Authentik.
 *
 * EL COLEGIO NO PIDE EL PERMISO: LO PIDE HEFESTO
 * ----------------------------------------------
 * Authentik demuestra quién es la persona. El `rol` en `usuarios` decide qué
 * puede ver. Las dos cosas son distintas y el software solo controla la
 * segunda.
 *
 * EL COLEGIO NO TIENE CONTRASEÑAS
 * ------------------------------
 * Todo vive en Authentik. Aquí no se guarda ninguna.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DescubridorOidc $descubridor,
        private readonly VerificadorIdToken $verificador,
        private readonly ResolvedorDeCuenta $resolvedor,
    ) {}

    /**
     * Parte el ingreso hacia Authentik, con PKCE.
     *
     * El estado y el nonce se guardan en la sesión y se comparan al volver.
     * Sin esa comparación, un `id_token` de otra sesión serviría para entrar.
     */
    public function login(Request $request): RedirectResponse
    {
        $issuer = (string) config('authentik.issuer');
        $documento = $this->descubridor->documento($issuer);

        $estado = Str::random(40);
        $nonce = Str::random(40);

        $session = $request->session();
        $session->put('oidc.estado', $estado);
        $session->put('oidc.nonce', $nonce);
        $session->put('oidc.redirigir_a', $this->destinoSeguro($request->query('redirigir_a')));

        // Verificador del authorization code, para PKCE (S256).
        $verificador = Str::random(64);
        $session->put('oidc.verificador', $verificador);

        $url = (string) $documento['authorization_endpoint'] . '?' . http_build_query([
            'client_id' => (string) config('authentik.client_id'),
            'redirect_uri' => (string) config('authentik.redirect_uri'),
            'response_type' => 'code',
            'scope' => (string) config('authentik.scopes'),
            'state' => $estado,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verificador, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($url);
    }

    /**
     * Vuelve de Authentik y abre sesión.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $session = $request->session();

        $estadoEsperado = (string) $session->pull('oidc.estado');
        $nonce = (string) $session->pull('oidc.nonce');
        $verificador = (string) $session->pull('oidc.verificador');
        $redirigir = (string) ($session->pull('oidc.redirigir_a') ?: '/');

        if ($estadoEsperado === '' || ! hash_equals($estadoEsperado, (string) $request->query('state', ''))) {
            return $this->fallo($request, 'La respuesta de Authentik no corresponde a esta petición.');
        }

        $codigo = (string) $request->query('code', '');

        if ($codigo === '') {
            return $this->fallo($request, 'Authentik no devolvió el código de autorización.');
        }

        try {
            $tokens = $this->canjearCodigo($codigo, $verificador);

            $claims = $this->verificador->verificar(
                (string) $tokens['id_token'],
                (string) config('authentik.issuer'),
                (string) config('authentik.client_id'),
                $nonce !== '' ? $nonce : null,
            );

            $usuario = $this->resolvedor->resolver($claims);
        } catch (RuntimeException $excepcion) {
            Log::warning('Falló el acceso.', ['error' => $excepcion->getMessage()]);

            return $this->fallo($request, 'No se pudo iniciar sesión.');
        }

        if ($usuario === null) {
            return $this->fallo(
                $request,
                'Su cuenta no está habilitada en ningún colegio. Hable con la secretaría.'
            );
        }

        // Una cuenta cuyo correo está en la ficha de una familia queda como apoderado.
        if ($usuario->rol === \App\Enums\RolUsuario::Comunicaciones && $usuario->apoderado_id === null) {
            $this->resolvedor->vincularComoApoderado($usuario, $usuario->tenant_id);
            $usuario->refresh();
        }

        // Regenerar el identificador de sesión: es la defensa contra el
        // ataque de fijación de sesión.
        $request->session()->regenerate();
        auth()->guard('web')->login($usuario);
        $usuario->forceFill(['ultimo_acceso' => now()])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'usuario_id' => $usuario->id,
                'rol' => $usuario->rol->value,
                'redirigir_a' => $redirigir,
            ]);
        }

        return redirect()->away($redirigir);
    }

    /** Cierra sesión. */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Sesión cerrada.']);
        }

        return redirect('/entrar');
    }

    /** Quién está conectado y qué puede hacer. */
    public function usuario(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'usuario' => [
                'id' => $usuario->id,
                'correo' => $usuario->correo,
                'nombre_completo' => $usuario->nombreCompleto(),
                'rol' => $usuario->rol->value,
                'rol_etiqueta' => $usuario->rol->etiqueta(),
                'es_del_colegio' => $usuario->esDelColegio(),
                've_conducta' => $usuario->veConducta(),
                'envia_comunicaciones' => $usuario->enviaComunicaciones(),
                'activo' => $usuario->activo,
            ],
            'colegio' => [
                'nombre' => $usuario->colegio?->nombre,
                'slug' => $usuario->colegio?->slug,
            ],
            // El BFF usa esto para decidir qué menús mostrar. No autoriza
            // nada: la autorización se decide en cada endpoint.
            'capacidades' => [
                'administra' => $usuario->administra(),
                've_todos_los_alumnos' => $usuario->veTodosLosAlumnos(),
                'registra_asistencia' => $usuario->esDocente(),
                'registra_conducta' => $usuario->registraConducta(),
                've_conducta' => $usuario->veConducta(),
                'envia_comunicaciones' => $usuario->enviaComunicaciones(),
                'gestiona_agenda' => $usuario->gestionaAgenda(),
            ],
        ]);
    }

    /**
     * Canjea el código de autorización por tokens.
     *
     * @return array<string, mixed>
     */
    private function canjearCodigo(string $codigo, string $verificador): array
    {
        $respuesta = Http::asForm()->timeout(10)->post(
            (string) $this->descubridor->documento((string) config('authentik.issuer'))['token_endpoint'],
            [
                'grant_type' => 'authorization_code',
                'code' => $codigo,
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'client_id' => (string) config('authentik.client_id'),
                'client_secret' => (string) config('authentik.client_secret'),
                'code_verifier' => $verificador,
            ]
        );

        if (! $respuesta->successful()) {
            throw new RuntimeException('Authentik no devolvió los tokens al canjear el código.');
        }

        $tokens = $respuesta->json();

        if (! is_array($tokens) || ! isset($tokens['id_token'])) {
            throw new RuntimeException('La respuesta de token de Authentik no trae id_token.');
        }

        return $tokens;
    }

    /**
     * Solo se aceptan rutas internas como destino.
     *
     * Una URL completa o un `//evil.example` convertirían el ingreso en una
     * redirección abierta: el atacante manda a la víctima a un sitio falso
     * justo después de iniciar sesión.
     */
    private function destinoSeguro(mixed $candidato): string
    {
        if (! is_string($candidato) || $candidato === '') {
            return '/';
        }

        if (str_starts_with($candidato, '/\\') || str_starts_with($candidato, '//')) {
            return '/';
        }

        if (! str_starts_with($candidato, '/')) {
            return '/';
        }

        return $candidato;
    }

    private function fallo(Request $request, string $mensaje): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje], 403);
        }

        return redirect('/entrar?error=' . urlencode($mensaje));
    }
}