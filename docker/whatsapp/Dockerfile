# =============================================================================
# Dockerfile — Servicio de WhatsApp Web (recordatorios de citas)
# Centro Médico de Flebología — Vens
# =============================================================================
# Node + Chromium. whatsapp-web.js maneja un WhatsApp Web de verdad dentro de
# un navegador sin ventana, así que la imagen necesita un Chromium completo.
#
# Se usa el Chromium de Debian y no el que descarga Puppeteer: el de Debian
# trae sus dependencias resueltas por apt y funciona también en Apple Silicon,
# donde Puppeteer no tiene binario para Linux ARM.
# =============================================================================

FROM node:22-bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive \
    PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    NODE_ENV=production

RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium \
        fonts-liberation \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /servicio

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

COPY index.js ./

# La sesión vinculada vive aquí; docker-compose monta un volumen para que
# sobreviva a los reinicios y no haya que volver a escanear el QR.
VOLUME ["/sesion"]

EXPOSE 3000

CMD ["node", "index.js"]
