# ---------------------------------------------------------------------------
# Imagen de la interfaz (Nuxt 4 con render en el servidor)
# ---------------------------------------------------------------------------
#
# Dos etapas: compilación y ejecución. La etapa de ejecución NO tiene Node
# instalado, así que el tamaño final es mucho menor y la superficie de
# ataque también.
#
# Referencia de versiones:
#   node:24.22.0-alpine3.24
#
# PENDIENTE ANTES DE PRODUCCIÓN
# -----------------------------
# Fijar por digest SHA256. Ver docs/10.
#
# ADVERTENCIA HONESTA: esta imagen NO se construyó. No había Docker en el
# entorno donde se escribió el código. Ver docs/10.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Etapa 1: compilar.
# ---------------------------------------------------------------------------
FROM node:24.22.0-alpine3.24 AS build

WORKDIR /app

# Se copian los manifiestos primero para que la capa de dependencias se
# reutilice cuando no cambian las versiones.
COPY package.json package-lock.json* ./

# `--ignore-scripts` porque `postinstall` corre `nuxt prepare`, que necesita
# el código fuente y todavía no está copiado.
RUN npm ci --ignore-scripts

COPY . .

ENV NUXT_TELEMETRY_DISABLED=1
RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 2: ejecutar.
# ---------------------------------------------------------------------------
FROM node:24.22.0-alpine3.24 AS app

WORKDIR /app

ENV NODE_ENV=production
ENV NUXT_TELEMETRY_DISABLED=1
ENV NITRO_HOST=0.0.0.0
ENV NITRO_PORT=3000

COPY --from=build /app/.output ./.output

# El usuario no es root.
USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]