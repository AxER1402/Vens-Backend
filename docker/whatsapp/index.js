/**
 * El puente entre Laravel y WhatsApp Web.
 *
 * WhatsApp no tiene una API gratuita para mandar mensajes desde un número
 * normal, así que este servicio hace lo mismo que la pestaña de WhatsApp Web:
 * abre un Chromium sin ventana, muestra un QR que se escanea con el teléfono
 * de la clínica y, desde ese momento, puede mandar mensajes en su nombre.
 *
 * Laravel no habla con WhatsApp: habla con este servicio por HTTP dentro de la
 * red de Docker. Por eso este proceso vive aparte y no dentro del contenedor
 * de la aplicación: un Chromium abierto de forma permanente no tiene nada que
 * ver con PHP, y si se cae no debe arrastrar a la API.
 *
 * La sesión se guarda en /sesion (un volumen), así que reiniciar el contenedor
 * no obliga a volver a escanear el QR.
 *
 *   GET  /estado          { estado, qr, numero }
 *   POST /enviar          { telefono, mensaje }  → { enviado: true }
 *   POST /cerrar-sesion   desvincula el teléfono y vuelve a pedir QR
 */
const express = require('express');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const PUERTO = Number(process.env.PORT || 3000);
const TOKEN = process.env.WHATSAPP_TOKEN || '';

/**
 * Dónde está la conexión.
 *
 *   iniciando       Chromium arrancando o recuperando la sesión guardada.
 *   esperando_qr    Hay un QR que escanear.
 *   conectado       Listo para mandar mensajes.
 *   desconectado    Se perdió la sesión; se reintenta solo.
 */
const estado = {
  estado: 'iniciando',
  qr: null,
  numero: null,
};

let cliente = null;
let reintento = null;

function crearCliente() {
  const nuevo = new Client({
    authStrategy: new LocalAuth({ dataPath: '/sesion' }),
    puppeteer: {
      executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
      headless: true,
      // Dentro de Docker Chromium no puede usar su sandbox (necesitaría
      // privilegios que el contenedor no tiene) y /dev/shm es muy pequeño.
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    },
  });

  nuevo.on('qr', async (qr) => {
    estado.estado = 'esperando_qr';
    estado.numero = null;
    // Se manda como imagen lista para pintar: así la pantalla no necesita
    // ninguna librería de QR, solo un <img>.
    estado.qr = await QRCode.toDataURL(qr, { margin: 1, width: 280 });
    console.log('[whatsapp] QR nuevo, esperando a que se escanee');
  });

  nuevo.on('authenticated', () => {
    estado.qr = null;
    console.log('[whatsapp] sesión autenticada');
  });

  nuevo.on('ready', () => {
    estado.estado = 'conectado';
    estado.qr = null;
    estado.numero = nuevo.info?.wid?.user ?? null;
    console.log(`[whatsapp] conectado como ${estado.numero}`);
  });

  nuevo.on('auth_failure', (mensaje) => {
    console.error('[whatsapp] falló la autenticación:', mensaje);
    reiniciar();
  });

  nuevo.on('disconnected', (motivo) => {
    console.warn('[whatsapp] desconectado:', motivo);
    reiniciar();
  });

  return nuevo;
}

/** Arranca un cliente nuevo; si falla, lo vuelve a intentar en un rato. */
async function iniciar() {
  clearTimeout(reintento);
  estado.estado = 'iniciando';
  estado.qr = null;
  estado.numero = null;

  cliente = crearCliente();

  try {
    await cliente.initialize();
  } catch (error) {
    console.error('[whatsapp] no se pudo iniciar:', error.message);
    reiniciar();
  }
}

/**
 * Tira el cliente actual y arranca otro.
 *
 * Tras perder la sesión, whatsapp-web.js deja el navegador en un estado del
 * que no se recupera solo; lo fiable es cerrarlo y empezar de nuevo, que
 * además genera el QR que hará falta para volver a vincular.
 */
function reiniciar(espera = 5000) {
  estado.estado = 'desconectado';
  estado.qr = null;
  estado.numero = null;

  clearTimeout(reintento);
  reintento = setTimeout(async () => {
    try {
      await cliente?.destroy();
    } catch {
      // Ya estaba cerrado: no hay nada que limpiar.
    }
    iniciar();
  }, espera);
}

const app = express();
app.use(express.json());

// Nadie fuera de la red de Docker debería llegar aquí, pero el token evita que
// cualquier otro contenedor de la misma red pueda mandar mensajes a nombre de
// la clínica.
app.use((req, res, next) => {
  if (TOKEN && req.get('X-Token') !== TOKEN) {
    return res.status(401).json({ error: 'Token inválido.' });
  }
  next();
});

app.get('/estado', (req, res) => {
  res.json(estado);
});

app.post('/enviar', async (req, res) => {
  const telefono = String(req.body?.telefono ?? '').replace(/\D+/g, '');
  const mensaje = String(req.body?.mensaje ?? '').trim();

  if (!telefono || !mensaje) {
    return res.status(422).json({ error: 'Faltan el teléfono o el mensaje.' });
  }

  if (estado.estado !== 'conectado') {
    return res.status(503).json({ error: 'WhatsApp no está conectado.' });
  }

  try {
    // getNumberId pregunta a WhatsApp si el número tiene cuenta. Mandar a uno
    // que no la tiene no da error: el mensaje se queda colgado para siempre.
    const destino = await cliente.getNumberId(telefono);

    if (!destino) {
      return res.status(404).json({ error: 'El número no tiene WhatsApp.' });
    }

    await cliente.sendMessage(destino._serialized, mensaje);
    return res.json({ enviado: true });
  } catch (error) {
    console.error('[whatsapp] no se pudo enviar:', error.message);
    return res.status(500).json({ error: 'No se pudo enviar el mensaje.' });
  }
});

app.post('/cerrar-sesion', async (req, res) => {
  try {
    if (estado.estado === 'conectado') {
      await cliente.logout();
    }
  } catch (error) {
    console.error('[whatsapp] error al cerrar la sesión:', error.message);
  }

  // Tanto si logout() funcionó como si no, se arranca de cero para que la
  // pantalla reciba un QR nuevo con el que vincular otro teléfono.
  reiniciar(1000);
  res.json(estado);
});

app.listen(PUERTO, () => {
  console.log(`[whatsapp] escuchando en el puerto ${PUERTO}`);
  iniciar();
});
