# 04 - Instalación

Requiere Docker con Compose v2. **No hace falta** PHP, Composer, Node ni
PostgreSQL en el equipo donde se levanta.

> **ADVERTENCIA HONESTA:** este procedimiento **no se ejecutó**. En el entorno
> donde se escribió el código no había Docker. Ver
> [10 - Pruebas y estado](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Preparar el entorno

```bash
cd apps/colegio
cp .env.example .env
```

Ningún valor de `.env.example` es real. Generar los secretos:

```bash
# APP_KEY
docker run --rm php:8.4-cli-alpine3.23 php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'

# AUTHENTIK_SECRET_KEY
openssl rand -base64 60 | tr -d '\n'

# AUTHENTIK_BOOTSTRAP__TOKEN
openssl rand -hex 32

# Contraseñas de base de datos
openssl rand -base64 32 | tr -d '\n'
```

Pegarlos en `.env`.

> **Ojo:** PostgreSQL no acepta contraseñas de más de **99 caracteres**. Es una
> limitación documentada por Authentik que muerde a los que usan secretos muy
> largos.

---

## 2. Levantar

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env up -d
```

| Servicio | URL |
|---|---|
| Interfaz | http://localhost:3000 |
| API | http://localhost:8000/api/v1 |
| Authentik | http://localhost:9000 |

La primera vez tarda: se bajan imágenes y se compila la interfaz.

---

## 3. Migraciones y verificación del aislamiento

```bash
# Crear el esquema y activar las políticas RLS
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate

# VERIFICACIÓN OBLIGATORIA. Debe responder "verificado correctamente".
docker compose -f deploy/docker-compose.dev.yml exec api php artisan colegio:verificar-aislamiento
```

Si este comando falla, **no sigas**. Significa que el aislamiento entre
colegios no está puesto, y la aplicación funcionaría perfectamente mostrando
datos de otros colegios.

---

## 4. Datos de ejemplo

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate --seed
```

Crea **dos** colegios con estructura completa: niveles, cursos, profesionales,
alumnos, familias, contactos de emergencia, matrículas, consentimientos,
comunicaciones enviadas con acuses, asistencia, conducta y agenda.

Se crean dos y no uno a propósito: **con un solo colegio no se puede
comprobar nada del aislamiento.**

> Los usuarios de ejemplo **no pueden entrar**: sus identidades en Authentik no
> existen (el `authentik_sub` es ficticio). Sirven para ver datos, no para
> probar el flujo de acceso. Ver sección 7.

---

## 5. Configurar Authentik

### 5.1 Crear el proveedor OAuth2

En la interfaz de Authentik:

1. **Applications** → **Create** → **OAuth2/OpenID Provider**
2. **Name**: `colegio`
3. **Protocol**: OpenID Connect
4. **Redirect URI**: `http://localhost:8000/api/v1/auth/callback`
5. **Scopes**: marcar `openid`, `profile`, `email`
6. Copiar el **Client ID** y el **Client Secret** al `.env`.
7. Copiar el **Issuer** (la URL que empieza con `https://.../application/o/`)
   a `AUTHENTIK_ISSUER`.

### 5.2 Flujo

Asignar al proveedor el flujo por defecto de autenticación con
autorización explícita.

**PKCE está habilitado en la aplicación.** Si el flujo de Authentik fuerza el
tipo de cliente `confidencial` sin PKCE, la aplicación genera el
`code_verifier` y el `code_challenge` por su cuenta, así que funciona igual.

### 5.3 Grupo de administración

Crear un grupo llamado `colegio-direccion` y asignarle a las personas de
dirección.

**Recordatorio importante:** el grupo de Authentik define el **primer alta**.
Una vez dentro, lo que decide es el `rol` en `usuarios`, que es del colegio.

---

## 6. Crear el primer colegio

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan colegio:crear
```

Pide: nombre, identificador, dominio de correo y el correo de quien será
dirección. Deja el colegio con estructura académica mínima y el primer usuario
de dirección.

> **Falta un paso que el comando no puede hacer:** completar `authentik_sub`
> con el valor real del token. Ver sección siguiente.

---

## 7 - El paso que casi nadie hace: `authentik_sub`

**Este es el paso donde la mayoría se queda pegada, y merece su propia
sección.**

La columna `usuarios.authentik_sub` identifica a la persona. **Si está
vacía, nadie puede entrar**, porque el ingreso busca la cuenta por ese valor y
no lo encuentra.

Hay dos caminos:

### Camino A: que la cuenta se cree sola

Si el correo de la persona **coincide con el dominio declarado por un colegio**,
la cuenta se da de alta automáticamente al primer ingreso, con rol
`comunicaciones` (el más acotado).

Después hay que **cambiar el rol** desde la base de datos o desde una sesión de
`php artisan tinker`.

### Camino B: completarlo a mano

1. Crear el usuario en Authentik con el correo exacto.
2. Iniciar sesión una vez por la URL de ingreso: la cuenta se crea con
   `authentik_sub` vacío.
3. Completar el valor:

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan tinker
```

```php
$usuario = App\Models\Usuario::where('correo', 'direccion@ejemplo.cl')->first();
$usuario->authentik_sub = '<valor de sub del token de Authentik>';
$usuario->rol = App\Enums\RolUsuario::Direccion;
$usuario->save();
```

> **`authentik_sub` está en `$hidden` del modelo y no aparece en ningún
> Resource.** Es intencional: es información interna de la integración y no
> tiene por qué viajar al navegador.

---

## 8. Correr las pruebas

```bash
# Crear la base de pruebas
docker compose -f deploy/docker-compose.dev.yml exec postgres \
  psql -U colegio_app -d postgres -c "CREATE DATABASE colegio_test;"

# Las dos suites críticas, y son las que hay que correr primero
docker compose -f deploy/docker-compose.dev.yml exec api php artisan test --testsuite=Aislamiento
docker compose -f deploy/docker-compose.dev.yml exec api php artisan test --testsuite=Apoderado

# El resto
docker compose -f deploy/docker-compose.dev.yml exec api php artisan test
```

Las pruebas **exigen PostgreSQL de verdad**, no SQLite: el aislamiento está
implementado con Row Level Security, que es específico de PostgreSQL. En SQLite
pasarían sin comprobar nada.

---

## 9. Problemas frecuentes

| Síntoma | Causa probable |
|---|---|
| `No hay contexto de tenant en la conexión` | Se consultó una tabla con RLS fuera del middleware. Revisar `ResolverTenant` |
| `colegio:verificar-aislamiento` dice que el rol salta RLS | El rol de la base es superusuario o tiene `Bypassrls`. Revisar `deploy/docker-compose.prod.yml` |
| Entra a la interfaz pero no ve nada | Cuenta sin `rol` asignado, o con `activo = false` |
| "No autorizado" al entrar | El dominio del correo no coincide con el declarado por ningún colegio |
| La lista de alumnos sale vacía para un docente | El profesional no tiene carga en el curso (`curso_profesional`) |
| El acuse no se guarda | La familia no fue destinataria del aviso (se rechaza a propósito) |

---

## 10. Qué haría distinto en producción

Ver [08 - Respaldos](08-RESPALDOS.md) y el `docker-compose.prod.yml`. Los
cuatro requisitos antes de levantar en producción:

1. `APP_ENV=production` y `APP_DEBUG=false`.
2. `SESSION_SECURE_COOKIE=true` (obligatorio: sin HTTPS no hay sesión).
3. Imágenes construidas y fijadas **por digest**.
4. Secretos reales en `.env.prod`, que **no se versiona**.