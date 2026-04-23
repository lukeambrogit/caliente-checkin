/**
 * Caliente Dance Studio — Live Check-in WebSocket Server
 *
 * Architecture:
 *   [WordPress] --HTTP POST /broadcast--> [this server] --WebSocket push--> [React dashboard]
 *
 * WordPress fires a non-blocking POST after each scan attempt (success or blocked).
 * All connected dashboard clients receive the event instantly.
 *
 * Env vars (.env):
 *   PORT          HTTP + WS port (default: 3001)
 *   WS_SECRET     Shared secret header value WordPress sends (required)
 *   ALLOWED_ORIGIN CORS origin for the React app (e.g. http://calientedancestudio.ro.test)
 */

require('dotenv').config();

const http = require('http');
const express = require('express');
const { WebSocketServer, OPEN } = require('ws');

const PORT = parseInt(process.env.PORT || '3001', 10);
const WS_SECRET = process.env.WS_SECRET || '';
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || '*';

if (!WS_SECRET) {
  console.warn('[WS] WARNING: WS_SECRET is not set. /broadcast endpoint is unprotected!');
}

const app = express();
app.use(express.json());

// CORS — only the WP site needs to POST here; browsers connect via WS
app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN);
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-WS-Secret');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// Health check
app.get('/health', (req, res) => {
  res.json({ ok: true, clients: countClients() });
});

/**
 * WordPress posts here after every scan attempt.
 * Body: { user_id, user_name, product_name, status, result_code, time, device_id, error }
 */
app.post('/broadcast', (req, res) => {
  const secret = req.headers['x-ws-secret'] || '';
  if (WS_SECRET && secret !== WS_SECRET) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const event = req.body;
  if (!event || typeof event !== 'object') {
    return res.status(400).json({ error: 'Invalid body' });
  }

  const msg = JSON.stringify({ type: 'checkin', data: event });
  let sent = 0;
  wss.clients.forEach((client) => {
    if (client.readyState === OPEN) {
      client.send(msg);
      sent++;
    }
  });

  console.log(`[WS] Broadcast to ${sent} client(s):`, event.user_name, event.status);
  res.json({ ok: true, clients: sent });
});

// Create HTTP server and attach WebSocket server to the same port
const server = http.createServer(app);
const wss = new WebSocketServer({ server });

wss.on('connection', (ws, req) => {
  const ip = req.socket.remoteAddress;
  console.log(`[WS] Client connected (${ip}), total: ${countClients()}`);

  ws.send(JSON.stringify({ type: 'connected', message: 'Live check-in feed connected.' }));

  ws.on('close', () => {
    console.log(`[WS] Client disconnected, remaining: ${countClients() - 1}`);
  });

  ws.on('error', (err) => {
    console.error('[WS] Client error:', err.message);
  });
});

function countClients() {
  return [...wss.clients].filter((c) => c.readyState === OPEN).length;
}

server.listen(PORT, () => {
  console.log(`[WS] Caliente WebSocket server running on port ${PORT}`);
  console.log(`[WS] Health: http://localhost:${PORT}/health`);
  console.log(`[WS] Broadcast endpoint: POST http://localhost:${PORT}/broadcast`);
});
