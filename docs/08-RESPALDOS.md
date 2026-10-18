# 08 - Respaldos

Un respaldo que nunca se restauró **no es un respaldo**: es una hopefulidad.
Este documento dice cómo se hace y, sobre todo, cómo se comprueba que sirve.

> **ADVERTENCIA HONESTA:** ninguno de estos comandos se ejecutó. No había
> Docker ni PostgreSQL en el entorno donde se escribió el código. Ver
> [10 - Pruebas y estado](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Qué hay que respaldar

| Volumen | Contenido | ¿Crítico? |
|---|---|---|
| `postgres-datos` | **Toda** la base: alumnos, familias, comunicaciones, acuses, bitácora | **Sí** |
| `authentik-postgres-datos` | Cuentas de acceso de Authentik | **Sí** |
| `authentik-media` | Imágenes de perfil y logos de Authentik | No |
| `caddy-datos` | Certificados TLS | No, se regeneran |
| `api-storage` | Logs de Laravel y archivos generados | Parcialmente |

**El volumen `postgres-datos` es el que importa.** Un colegio sin base de
datos tiene que reconstruir todas sus fichas de alumno y todo su historial de
comunicaciones a mano, y eso no es recuperable en un plazo razonable.

---

## 2. Por qué `pg_dump` y no copiar el volumen

Copiar el directorio de PostgreSQL mientras el servidor está corriendo
produce un respaldo **inconsistente**: PostgreSQL escribe de forma continua y
las páginas copiadas pueden no calzar entre sí.

`pg_dump` hace un respaldo **consistente** aunque la base siga en uso.

**El único caso donde tiene sentido copiar el volumen** es con el contenedor
detenido:

```bash
docker compose -f deploy/docker-compose.prod.yml stop postgres
tar czf postgres-$(date +%F).tar.gz <ruta-del-volumen>
docker compose -f deploy/docker-compose.prod.yml start postgres
```

---

## 3. Respaldo del colegio

```bash
# Volcado de texto plano, formato propio de PostgreSQL
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists \
  > "respaldo-colegio-$(date +%F).sql"
```

### Opciones que importan

| Opción | Por qué |
|---|---|
| `--clean --if-exists` | El respaldo se puede restaurar sobre una base existente |
| `--no-owner` | El dumped no arrastra el rol owner; permite restaurarlo con un rol distinto |
| `--format=custom` | Formato binario, comprimido y **restaurable en paralelo**. Mejor opción para datos grandes |

Para un colegio chico, el formato de texto con `gzip` basta:

```bash
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists \
  | gzip > "respaldo-colegio-$(date +%F).sql.gz"
```

---

## 4. Restauración

```bash
gunzip -c respaldo-colegio-2026-03-10.sql.gz | \
  docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  psql -U "$DB_USERNAME" -d "$DB_DATABASE"
```

### Y después, SIEMPRE

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan colegio:verificar-aislamiento
```

Restaurar una base y no verificar el aislamiento es una forma de tener datos
sin protección y creer que están protegidos.

---

## 5. La prueba de restauración

**Esta es la parte que casi nadie hace, y es la única que importa.**

Un respaldo que nunca se restauró es una hipótesis.

Cada mes, en un ambiente que **no** sea producción:

1. Restaurar el último respaldo.
2. Contar alumnos y verificar que el número es el esperado.
3. Verificar que las RLS están activas.
4. Entrar como dos colegios distintos y confirmar que cada uno ve lo suyo.
5. **Borrar ese ambiente.**

Que el paso 4 se haga **con dos colegios reales del respaldo** es lo que
comprueba que el aislamiento sobrevive a una restauración. Una base restaurada
puede quedar con las políticas mal copiadas si el respaldo se hizo antes de
activarlas.

---

## 6. Automatización

```bash
#!/bin/sh
# Respaldo diario del colegio.
#
# Ubicación sugerida: /opt/colegio/respaldo-diario.sh
#
# Los respaldos se guardan FUERA del servidor, en un lugar que no dependa de
# este servidor. Un respaldo en el mismo disco que la base no es un respaldo.

set -e

FECHA=$(date +%F)
DESTINO=/var/backups/colegio
RETENCION_DIAS=30

mkdir -p "$DESTINO"

docker compose -f /opt/colegio/deploy/docker-compose.prod.yml exec -T postgres \
  pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists \
  | gzip > "$DESTINO/colegio-$FECHA.sql.gz"

# Borra los respaldos viejos.
find "$DESTINO" -name 'colegio-*.sql.gz' -mtime +$RETENCION_DIAS -delete
```

En cron:

```cron
0 2 * * * /opt/colegio/respaldo-diario.sh >> /var/log/colegio-respaldo.log 2>&1
```

---

## 7. Cifrado

**Los respaldos contienen datos personales de menores**, guardados en texto
plano. Deben ir cifrados en reposo y en tránsito.

```bash
# Cifrado simétrico del archivo
age -r age-secreto-respaldo.txt \
    -o "respaldo-colegio-$(date +%F).sql.gz.age" \
    "respaldo-colegio-$(date +%F).sql.gz"

# Y en tránsito
scp respaldo-colegio-2026-03-10.sql.gz.age respaldo@servidor-externo:/respaldos/
```

**La clave pública de cifrado tiene que estar en un lugar distinto del
respaldo.** Si se guardan juntos, el respaldo está en claro para quien tenga
acceso al disco.

---

## 8. Verificación del respaldo

Un respaldo que no se verifica se pudre en silencio.

```bash
# El archivo no está corrupto
gunzip -t respaldo-colegio-2026-03-10.sql.gz && echo "Íntegro"

# Y contiene algo (un respaldo vacío "funciona" perfectamente)
gunzip -c respaldo-colegio-2026-03-10.sql.gz | grep -c "COPY public.alumnos"
```

Si esa última línea devuelve `0`, el respaldo está vacío. Es más probable de
lo que parece cuando el comando falla en silencio dentro de un script.

---

## 9. Autenticación e imágenes

| Volumen | Cómo respaldar |
|---|---|
| `authentik-postgres-datos` | Igual que la base del colegio, con `pg_dump` |
| `authentik-media` | `tar czf authentik-media-$(date +%F).tar.gz` con el contenedor detenido |
| Imágenes de la aplicación | El registro de imágenes, no el servidor |

Las imágenes Docker deberían estar en un registro con etiqueta fija, y
construidas **por digest**. Un servidor que depende de construir desde el
código fuente necesita el código para reconstruirse.

---

## 10. Pendiente conocido

**En esta versión no hay script de respaldo en el repositorio.** Lo que hay es
el procedimiento documentado acá y el esbozo de la sección 6.

Falta, y hay que hacer antes de producción:

1. Un script de respaldo versionado y probado.
2. Verificación automática de integridad (que falle si el respaldo está vacío).
3. Copia externa verificada.
4. La prueba de restauración mensual automatizada.
5. Una política de retención acordada con el colegio (por defecto 30 días).