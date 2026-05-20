'use strict';

const crypto = require('node:crypto');
const { Server } = require('socket.io');

function base64UrlDecode(value) {
  const normalized = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
  const padded = normalized + '='.repeat((4 - (normalized.length % 4 || 4)) % 4);
  return Buffer.from(padded, 'base64').toString('utf8');
}

function verifyJwt(token, secret) {
  const parts = String(token || '').split('.');
  if (parts.length !== 3) {
    throw new Error('Invalid token');
  }

  const [header, payload, signature] = parts;
  const expected = crypto
    .createHmac('sha256', secret)
    .update(`${header}.${payload}`)
    .digest('base64url');

  if (expected !== signature) {
    throw new Error('Invalid signature');
  }

  const claims = JSON.parse(base64UrlDecode(payload));
  const now = Math.floor(Date.now() / 1000);
  if (claims.exp && claims.exp < now) {
    throw new Error('Token expired');
  }

  return claims;
}

function createSocketGateway({ httpServer, env, log }) {
  const io = new Server(httpServer, {
    path: env.SOCKET_IO_PATH || '/socket.io',
    cors: {
      origin: env.CORS_ORIGIN === '*' ? true : String(env.CORS_ORIGIN || '').split(',').map((item) => item.trim()).filter(Boolean),
      credentials: true,
    },
    transports: ['websocket', 'polling'],
  });

  const connections = new Map();

  io.use((socket, next) => {
    try {
      const claims = verifyJwt(socket.handshake.auth?.token || socket.handshake.headers.authorization?.replace(/^Bearer\s+/i, ''), env.JWT_SECRET);
      if (!claims.company_id) {
        throw new Error('Tenant required');
      }

      socket.data.auth = claims;
      next();
    } catch (error) {
      next(error);
    }
  });

  io.on('connection', (socket) => {
    const auth = socket.data.auth || {};
    const companyId = Number(auth.company_id || 0);
    const userId = Number(auth.user_id || 0);
    const endpointId = auth.endpoint_id ? String(auth.endpoint_id) : '';

    socket.join(`tenant:${companyId}`);
    if (userId > 0) socket.join(`user:${userId}`);
    if (endpointId !== '') socket.join(`endpoint:${endpointId}`);

    connections.set(socket.id, { companyId, userId, endpointId });
    log('info', 'socket.connected', { socket_id: socket.id, company_id: companyId, user_id: userId });

    socket.on('disconnect', () => {
      connections.delete(socket.id);
      log('info', 'socket.disconnected', { socket_id: socket.id, company_id: companyId, user_id: userId });
    });
  });

  return {
    io,
    emitTenant(companyId, event, payload) {
      io.to(`tenant:${companyId}`).emit(event, payload);
    },
    emitUser(userId, event, payload) {
      io.to(`user:${userId}`).emit(event, payload);
    },
    connections(companyId = null) {
      if (companyId === null) return connections.size;
      return Array.from(connections.values()).filter((row) => row.companyId === companyId).length;
    },
  };
}

module.exports = { createSocketGateway };
