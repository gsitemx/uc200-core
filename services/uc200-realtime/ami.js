'use strict';

const AmiClient = require('asterisk-ami-client');

function createAmiGateway({ env, log, onEvent }) {
  const client = new AmiClient({
    reconnect: false,
    keepAlive: true,
    emitEventsByTypes: false,
    eventTypeToLowerCase: false,
  });

  let connected = false;
  let reconnectTimer = null;
  let pingTimer = null;

  const scheduleReconnect = () => {
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connect().catch((error) => {
        log('error', 'ami.connect.failed', { message: error.message });
        scheduleReconnect();
      });
    }, Number(env.AMI_RECONNECT_DELAY_MS || 3000));
  };

  const startHeartbeat = () => {
    stopHeartbeat();
    pingTimer = setInterval(async () => {
      try {
        await client.action({ Action: 'Ping' });
        log('debug', 'ami.ping.ok');
      } catch (error) {
        log('error', 'ami.ping.failed', { message: error.message });
        connected = false;
        scheduleReconnect();
      }
    }, Number(env.AMI_PING_INTERVAL_MS || 15000));
  };

  const stopHeartbeat = () => {
    if (pingTimer) {
      clearInterval(pingTimer);
      pingTimer = null;
    }
  };

  const bindEvents = () => {
    client.on('event', (event) => {
      onEvent(event);
    });

    client.on('disconnect', () => {
      connected = false;
      stopHeartbeat();
      log('warn', 'ami.disconnected');
      scheduleReconnect();
    });

    client.on('incorrectServer', () => {
      connected = false;
      log('error', 'ami.incorrect_server');
      scheduleReconnect();
    });

    client.on('error', (error) => {
      connected = false;
      stopHeartbeat();
      log('error', 'ami.error', { message: error.message });
      scheduleReconnect();
    });
  };

  const connect = async () => {
    log('info', 'ami.connecting', {
      host: env.AMI_HOST,
      port: Number(env.AMI_PORT || 5038),
      username: env.AMI_USERNAME,
    });

    await client.connect(env.AMI_USERNAME, env.AMI_PASSWORD, {
      host: env.AMI_HOST,
      port: Number(env.AMI_PORT || 5038),
    });

    connected = true;
    log('info', 'ami.connected');
    startHeartbeat();
  };

  bindEvents();

  return {
    async start() {
      await connect().catch((error) => {
        log('error', 'ami.start.failed', { message: error.message });
        scheduleReconnect();
      });
    },
    async stop() {
      stopHeartbeat();
      try {
        await client.disconnect();
      } catch (error) {
        log('warn', 'ami.stop.disconnect_failed', { message: error.message });
      }
    },
    status() {
      return {
        connected,
        reconnect_scheduled: Boolean(reconnectTimer),
      };
    },
  };
}

module.exports = { createAmiGateway };
