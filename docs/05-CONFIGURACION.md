# 05 - Configuración

Este documento lista todas las variables de entorno, para qué sirve cada una
y qué pasa si está mal.

---

## 1. Dónde vive cada ajuste

| Tipo | Dónde | Ejemplo |
|---|---|---|
| Ajuste de infraestructura | `.env` | `DB_PASSWORD` |
| Ajuste de la aplicación | `.env` + `config/colegio.php` | `COLEGIO_ROLES_CONDUCTA` |
| Ajuste del proveedor de identidad | `.env` + `config/authentik.php` | `AUTHENTIK_ISSUER` |
| Regla de negocio | Código, en el enum | Qué roles pueden leer conducta |

El criterio: **si un colegio tiene que decidirlo, va en variable de
entorno.** Si es una regla del sistema, va en código.

---

## 2. Aplicación

| Variable | Por defecto | Para qué |
|---|---|---|
| `APP_ENV` | `local` | `local`, `production`, `testing` |
| `APP_KEY` | (vacío) | Cifra la sesión y los datos de Laravel. **Generar.** |
| `APP_URL` | `http://localhost:8000` | URL interna de la API |
| `APP_DEBUG` | `true` en dev | **En producción siempre `false`**: muestra datos internos en los errores |
| `APP_WEB_URL` | `http://localhost:3000` | Origen público de la interfaz |

### Sobre `APP_KEY`

Si se filtra, hay que **rotarla**, y eso **invalida todas las sesiones**:
todo el mundo queda desconectado. No hay rotación automática.

---

## 3. Base de datos

| Variable | Por defecto | Para qué |
|---|---|---|
| `DB_DATABASE` | `colegio` | Nombre de la base |
| `DB_USERNAME` | `colegio_app` | **NO puede ser superusuario ni tener `BYPASSRLS`** |
| `DB_PASSWORD` | (vacío) | Contraseña |
| `DB_HOST` | `postgres` | Servicio de Docker |
| `DB_PORT` | `5432` | Puerto |
| `DB_SSLMODE` | `prefer` | `disable`, `prefer`, `require` |

### El requisito que no se puede omitir

> El rol de esta conexión **no puede ser superusuario ni tener `BYPASSRLS`**.

Con cualquiera de las dos cosas, PostgreSQL **ignora las políticas RLS por
completo**: un colegio vería los datos de todos los demás y la aplicación no
tendría cómo enterarse.

`php artisan colegio:verificar-aislamiento` lo comprueba y falla.

### Sobre `DB_SSLMODE`

`prefer` no cifra: usa TLS solo si el servidor lo ofrece. Si el servidor de
base de datos está en otra máquina, hay que poner `require`. **Estos datos son
de menores**: van en claro o no van.

---

## 4. Redis

| Variable | Para qué |
|---|---|
| `REDIS_HOST` | Servicio de Docker |
| `REDIS_PORT` | `6379` |
| `REDIS_PASSWORD` | **Obligatorio en producción** |
| `REDIS_CACHE_DB` | Base de datos de cache (por defecto `1`) |

Redis guarda el documento de descubrimiento de Authentik y el JWKS, y las
colas. **No guarda datos de alumnos.**

Si Authentik no responde, el descubrimiento usa el valor guardado en lugar de
fallar: que el proveedor esté caído no debería impedir entrar a quien ya tiene
sesión.

---

## 5. Authentik (proveedor de identidad)

| Variable | Para qué |
|---|---|
| `AUTHENTIK_ISSUER` | URL base del proveedor OIDC |
| `AUTHENTIK_CLIENT_ID` | Cliente |
| `AUTHENTIK_CLIENT_SECRET` | Secreto del cliente |
| `AUTHENTIK_REDIRECT_URI` | Debe terminar en `/api/v1/auth/callback` |
| `AUTHENTIK_SCOPES` | `openid profile email` |
| `AUTHENTIK_GRUPO_ADMIN` | Grupo que define el **primer alta**. No da permiso permanente |
| `AUTHENTIK_ALGORITMO` | **El que se ESPERA**, no el que venga. `RS256` |
| `AUTHENTIK_VENTANA_MINUTOS` | Minutos de validez del intento de ingreso |

### Sobre `AUTHENTIK_ALGORITMO`

Se espera un algoritmo **fijo**. Aceptar el que venga en la cabecera cerraría
el ataque de confusión de algoritmo: firmar el token con la clave pública
usando `HS256`.

---

## 6. Ajustes propios del colegio

Viven en `config/colegio.php`.

### `COLEGIO_RETENCION_BITACORA_HORAS`

```dotenv
COLEGIO_RETENCION_BITACORA_HORAS=8760
```

Cuánto tiempo se conserva la bitácora de accesos. Por defecto un año.

**La decisión final es del colegio con su asesoría legal.** La bitácora es a
la vez un repositorio de datos personales (IP, agente de usuario, y sobre todo
a qué alumno se accedió).

El recorte corre solo, una vez al día a las 03:00. **Lo que se borra no se
recupera.**

### `COLEGIO_ROLES_CONDUCTA`

```dotenv
COLEGIO_ROLES_CONDUCTA=direccion,orientacion,docente
```

Qué roles pueden **leer** el registro de incidentes de conducta.

Es configurable, y no por convenience: en un colegio chico la dirección y la
orientadora suelen ser la misma persona. Si el rol quedara fijo en el código,
esa persona podría registrar un incidente y **no poder volver a leerlo**.

**Ojo:** esta lista es de **lectura**, no de registro. Para registrar
incidentes, los roles son `docente` y `orientacion`, y eso sí está fijo en
`RolUsuario::registraConducta()`.

Valores válidos: `direccion`, `administracion`, `docente`, `comunicaciones`,
`orientacion`, `apoderado`.

> **Recomendación con datos reales:** no agregar `apoderado` a esta lista sin
> una decisión legal escrita del colegio. Ver
> [07](07-SEGURIDAD-Y-LEY-19628.md), sección 5.3c.

### `COLEGIO_POR_PAGINA_MAXIMO`

```dotenv
COLEGIO_POR_PAGINA_MAXIMO=100
```

Techo duro del tamaño de página. Evita que alguien pida toda la tabla de una
sola vez.

---

## 7. Interfaz (Nuxt)

| Variable | Dónde se usa | Para qué |
|---|---|---|
| `NUXT_API_BASE` | **Solo servidor** | Dirección interna de la API |
| `NUXT_PUBLIC_BASE` | Cliente y servidor | Origen público de entrada |

### `NUXT_API_BASE` es privada

Solo la usa el servidor de Nuxt, para saber a dónde reenviar. **Nunca debe
exponerse al navegador**, porque revelaría la dirección interna de la API y
permitiría saltarse el BFF.

---

## 8. Revisión antes de publicar

Nunca subir un `.env`. Antes de un commit:

```bash
git status --short | grep -E '\.env$'
```

Y revisar que `.env.example` **no tenga valores reales**: solo nombres de
variable y valores de ejemplo que no sirvan para nada.

`composer.lock` **sí** se versiona (reproducibilidad). `.env` nunca.

---

## 9. Aplicar cambios de configuración

Después de cambiar cualquier variable:

```bash
docker compose -f deploy/docker-compose.dev.yml restart api
```

En producción, además hay que reconstruir la imagen: las variables se
incorporan al construir, no se leen en tiempo de ejecución.

> **Pendiente conocido:** el compose de producción no tiene equivalente de
> `php artisan config:cache`. Con la configuración cacheada, una variable
> cambiada no surte efecto hasta que se limpie la caché. Hay que agregarlo al
> procedimiento de despliegue antes de producción. Ver
> [10](10-PRUEBAS-Y-ESTADO.md).