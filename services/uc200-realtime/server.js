'use strict';

require('dotenv').config();

const { createServer } = require('node:http');
const express = require('express');
const cors = require('cors');
const { createAmiGateway } = require('./ami');
const { createSocketGateway } = require('./socket');
const { createPresenceStore } = require('./presence');
const { createQueueStore } = require('./queue');

const env = {
  PORT: Number(process.env.PORT || 3100),
  HOST: process.env.HOST || '127.0.0.1',
  CORS_ORIGIN: process.env.CORS_ORIGIN || '*',
  SOCKET_IO_PATH: process.env.SOCKET_IO_PATH || '/socket.io',
  AMI_HOST: process.env.AMI_HOST || '127.0.0.1',
  AMI_PORT: Number(process.env.AMI_PORT || 5038),
  AMI_USERNAME: process.env.AMI_USERNAME || 'admin',
  AMI_PASSWORD: process.env.AMI_PASSWORD || '',
  AMI_RECONNECT_DELAY_MS: Number(process.env.AMI_RECONNECT_DELAY_MS || 3000),
  AMI_PING_INTERVAL_MS: Number(process.env.AMI_PING_INTERVAL_MS || 15000),
  JWT_SECRET: process.env.JWT_SECRET || '',
  LOG_LEVEL: process.env.LOG_LEVEL || 'info',
  REDIS_URL: process.env.REDIS_URL || '',
};

if (!env.JWT_SECRET) {
  throw new Error('JWT_SECRET is required for uc200-realtime.');
}

const log = (level, event, context = {}) => {
  const levels = ['debug', 'info', 'warn', 'error'];
  const current = levels.indexOf(env.LOG_LEVEL);
  const target = levels.indexOf(level);
  if (target < current) return;

  process.stdout.write(`${JSON.stringify({
    ts: new Date().toISOString(),
    level,
    event,
    ...context,
  })}\n`);
};

const app = express();
app.use(cors({
  origin: env.CORS_ORIGIN === '*' ? true : env.CORS_ORIGIN.split(',').map((item) => item.trim()).filter(Boolean),
  credentials: true,
}));
app.use(express.json());

const httpServer = createServer(app);
const socket = createSocketGateway({ httpServer, env, log });
const presence = createPresenceStore({ log });
const queues = createQueueStore();

const emitStats = (companyId) => {
  const presenceStats = presence.stats(companyId);
  const queueStats = queues.stats(companyId);
  socket.emitTenant(companyId, 'realtime_stats', {
    company_id: companyId,
    active_calls: presenceStats.active_calls,
    extensions_online: presenceStats.extensions_online,
    agents_online: queueStats.agents_online,
    queues_waiting: queueStats.queues_waiting,
    socket_clients: socket.connections(companyId),
    updated_at: new Date().toISOString(),
  });
};

const emitCallLifecycle = (payload) => {
  if (!payload || !payload.company_id) return;

  const eventName = payload.state === 'ringing'
    ? 'call_started'
    : payload.state === 'active'
      ? 'call_answered'
      : payload.state === 'ended'
        ? 'call_ended'
        : null;

  if (eventName) {
    socket.emitTenant(payload.company_id, eventName, payload);
  }
};

app.get('/health', (_request, response) => {
  response.json({
    status: 'ok',
    socket_clients: socket.connections(),
    redis_ready: Boolean(env.REDIS_URL),
    updated_at: new Date().toISOString(),
  });
});

const ami = createAmiGateway({
  env,
  log,
  onEvent(event) {
    const presenceEvents = presence.handle(event);
    presenceEvents.extension_status.forEach((payload) => {
      socket.emitTenant(payload.company_id, 'extension_status', payload);
    });
    presenceEvents.active_call.forEach((payload) => {
      socket.emitTenant(payload.company_id, 'active_call', payload);
      emitCallLifecycle(payload);
    });
    presenceEvents.call_popup.forEach((payload) => {
      socket.emitTenant(payload.company_id, 'call_popup', payload);
    });
    presenceEvents.stats_tenants.forEach((companyId) => emitStats(companyId));

    queues.handle(event).forEach((payload) => {
      socket.emitTenant(payload.company_id, 'queue_update', payload);
      emitStats(payload.company_id);
    });
  },
});

httpServer.listen(env.PORT, env.HOST, async () => {
  log('info', 'realtime.listening', {
    host: env.HOST,
    port: env.PORT,
    socket_path: env.SOCKET_IO_PATH,
  });
  await ami.start();
});

process.on('SIGTERM', async () => {
  log('info', 'realtime.shutdown');
  await ami.stop();
  httpServer.close(() => process.exit(0));
});

process.on('SIGINT', async () => {
  log('info', 'realtime.shutdown');
  await ami.stop();
  httpServer.close(() => process.exit(0));
});
