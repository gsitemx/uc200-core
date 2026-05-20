(() => {
  const config = window.UC200RealtimeConfig || {};
  if (!config.tokenEndpoint) return;

  const state = {
    connected: false,
    socket: null,
    presence: new Map(),
    activeCalls: new Map(),
    queues: new Map(),
    stats: {},
  };

  const normalizeStatus = (value) => {
    const status = String(value || '').toLowerCase();
    if (['available', 'online', 'registered', 'reachable'].includes(status)) return 'available';
    if (['busy', 'inuse', 'in-use'].includes(status)) return 'busy';
    if (['ringing', 'ring'].includes(status)) return 'ringing';
    if (['away', 'dnd'].includes(status)) return status;
    return 'offline';
  };

  const badgeClass = (status) => ['available', 'online', 'registered', 'reachable'].includes(status) ? 'on' : '';

  const setConnectionBadge = (text, connected) => {
    document.querySelectorAll('[data-realtime-connection]').forEach((badge) => {
      badge.textContent = text;
      badge.classList.toggle('on', Boolean(connected));
    });
  };

  const dispatch = (name, detail) => {
    document.dispatchEvent(new CustomEvent(`uc200:${name}`, { detail }));
  };

  const updatePresenceDom = (payload) => {
    const endpointId = String(payload.endpoint_id || '');
    const status = normalizeStatus(payload.status);
    if (!endpointId) return;

    document.querySelectorAll(`[data-realtime-presence][data-endpoint-id="${CSS.escape(endpointId)}"]`).forEach((node) => {
      node.textContent = status;
      node.classList.toggle('on', badgeClass(status) === 'on');
      node.dataset.presenceStatus = status;
    });
  };

  const updateStatsDom = () => {
    Object.entries(state.stats || {}).forEach(([key, value]) => {
      document.querySelectorAll(`[data-realtime-stat-value="${CSS.escape(key)}"]`).forEach((node) => {
        node.textContent = `${value}`;
      });
    });
  };

  const ensurePopup = () => {
    let popup = document.querySelector('[data-global-call-popup]');
    if (popup) return popup;

    popup = document.createElement('div');
    popup.dataset.globalCallPopup = '1';
    popup.className = 'incoming-call-popup';
    popup.hidden = true;
    popup.innerHTML = `
      <strong data-global-call-title>Llamada entrante</strong>
      <p data-global-call-copy style="margin:4px 0 10px; color:var(--muted);"></p>
      <div>
        <a class="button primary xs" href="/softphone">Abrir softphone</a>
        <button class="button secondary xs" type="button" data-global-call-close>Cerrar</button>
      </div>
    `;
    document.body.appendChild(popup);
    popup.querySelector('[data-global-call-close]')?.addEventListener('click', () => {
      popup.hidden = true;
    });
    return popup;
  };

  const showPopup = (payload) => {
    const popup = ensurePopup();
    const title = popup.querySelector('[data-global-call-title]');
    const copy = popup.querySelector('[data-global-call-copy]');
    if (title) title.textContent = payload.contact_name || payload.remote_number || payload.destination || 'Llamada entrante';
    if (copy) {
      const parts = [payload.company_name, payload.direction, payload.queue].filter(Boolean);
      copy.textContent = parts.join(' / ');
    }
    popup.hidden = false;
  };

  const loadClient = (url) => new Promise((resolve, reject) => {
    if (window.io) {
      resolve(window.io);
      return;
    }

    const script = document.createElement('script');
    script.src = url;
    script.async = true;
    script.onload = () => (window.io ? resolve(window.io) : reject(new Error('socket.io client missing')));
    script.onerror = () => reject(new Error(`Unable to load ${url}`));
    document.head.appendChild(script);
  });

  const fetchJson = async (url) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
  };

  const connect = async () => {
    try {
      setConnectionBadge('Realtime loading', false);
      const payload = await fetchJson(config.tokenEndpoint);
      if (!payload.success) {
        setConnectionBadge('Realtime unavailable', false);
        return;
      }

      const data = payload.data || {};
      const socketConfig = data.socket || {};
      const baseUrl = socketConfig.url || window.location.origin;
      const path = socketConfig.path || '/socket.io';
      const scriptUrl = socketConfig.script_url
        ? (socketConfig.script_url.startsWith('http') ? socketConfig.script_url : `${baseUrl}${socketConfig.script_url}`)
        : `${baseUrl}${path.replace(/\/$/, '')}/socket.io.js`;

      const io = await loadClient(scriptUrl);
      const socket = io(baseUrl, {
        path,
        transports: socketConfig.transports || ['websocket'],
        auth: { token: data.token },
        reconnection: true,
        reconnectionAttempts: Infinity,
      });

      state.socket = socket;

      socket.on('connect', () => {
        state.connected = true;
        setConnectionBadge('Realtime online', true);
        dispatch('connected', { socketId: socket.id });
      });

      socket.on('disconnect', () => {
        state.connected = false;
        setConnectionBadge('Realtime offline', false);
        dispatch('disconnected', {});
      });

      socket.on('presence_snapshot', (rows = []) => {
        rows.forEach((row) => {
          if (!row.endpoint_id) return;
          state.presence.set(String(row.endpoint_id), row);
          updatePresenceDom(row);
        });
        dispatch('presence_snapshot', rows);
      });

      socket.on('extension_status', (payload) => {
        if (payload?.endpoint_id) {
          state.presence.set(String(payload.endpoint_id), payload);
          updatePresenceDom(payload);
        }
        dispatch('extension_status', payload);
      });

      socket.on('active_call', (payload) => {
        const key = String(payload?.linkedid || payload?.uniqueid || Date.now());
        state.activeCalls.set(key, payload);
        dispatch('active_call', payload);
      });

      ['call_started', 'call_answered', 'call_held', 'call_transferred', 'call_ended', 'call_parked'].forEach((eventName) => {
        socket.on(eventName, (payload) => {
          dispatch(eventName, payload);
        });
      });

      socket.on('queue_update', (payload) => {
        const key = String(payload?.queue || payload?.queue_name || payload?.company_id || Date.now());
        state.queues.set(key, payload);
        dispatch('queue_update', payload);
      });

      socket.on('call_popup', (payload) => {
        showPopup(payload || {});
        dispatch('call_popup', payload);
      });

      socket.on('realtime_stats', (payload) => {
        state.stats = payload || {};
        updateStatsDom();
        dispatch('realtime_stats', payload);
      });
    } catch (error) {
      console.warn('UC200 realtime bootstrap failed', error);
      setConnectionBadge('Realtime unavailable', false);
    }
  };

  window.UC200Realtime = {
    state,
    connect,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', connect, { once: true });
  } else {
    connect();
  }
})();
