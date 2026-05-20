(function () {
  const root = document.querySelector('[data-softphone-root]');
  if (!root) return;
  const SIP = window.SIP || null;
  const resolveWebSocketUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return `wss://${window.location.host}/ws`;
    if (raw.startsWith('ws://') || raw.startsWith('wss://')) return raw;
    if (raw.startsWith('/')) return `wss://${window.location.host}${raw}`;
    return `wss://${window.location.host}/${raw.replace(/^\/+/, '')}`;
  };

  const state = {
    ua: null,
    registerer: null,
    session: null,
    consultSession: null,
    token: null,
    sipDomain: null,
    preferences: {},
    callStartedAt: null,
    timerId: null,
    muted: false,
    held: false,
    ringContext: null,
    ringOscillator: null,
    lastRemoteNumber: null,
    currentCall: null
  };

  const $ = (selector) => document.querySelector(selector);
  const statusBadge = $('[data-phone-state]');
  const extensionSelect = $('[data-extension-select]');
  const targetInput = $('[data-dial-target]');
  const remoteAudio = $('[data-remote-audio]');
  const timer = $('[data-call-timer]');
  const contactName = $('[data-contact-name]');
  const contactCompany = $('[data-contact-company]');
  const contactStatus = $('[data-contact-status]');
  const contactHistory = $('[data-contact-history]');
  const currentCallName = $('[data-current-call-name]');
  const currentCallMeta = $('[data-current-call-meta]');
  const currentCallState = $('[data-current-call-state]');
  const currentCallAvatar = $('[data-current-call-avatar]');
  const selectedOption = () => extensionSelect?.selectedOptions?.[0] || null;
  const selectedOrigin = () => selectedOption()?.dataset.endpointId || '';
  const selectedCompanyId = () => selectedOption()?.dataset.companyId || '';

  const setState = (value) => {
    if (statusBadge) statusBadge.textContent = value;
  };

  const updateCurrentCallCard = (payload = null) => {
    if (!currentCallName || !currentCallMeta || !currentCallState || !currentCallAvatar) return;

    if (!payload) {
      currentCallName.textContent = 'Listo para llamar';
      currentCallMeta.textContent = 'Selecciona una identidad UC200 y usa el cliente web como experiencia principal.';
      currentCallState.textContent = 'idle';
      currentCallState.className = 'badge';
      currentCallAvatar.textContent = 'UC';
      return;
    }

    const remote = payload.contact_name || payload.remote_number || payload.destination || 'Llamada activa';
    currentCallName.textContent = remote;
    currentCallMeta.textContent = [
      payload.company_name || selectedOption()?.textContent?.split('/')[1]?.trim() || '',
      payload.channel || payload.direction || ''
    ].filter(Boolean).join(' / ');
    currentCallState.textContent = payload.state || 'active';
    currentCallState.className = `badge ${['active', 'answered'].includes(String(payload.state || '')) ? 'on live' : ['ringing', 'hold'].includes(String(payload.state || '')) ? 'warn' : ''}`;
    currentCallAvatar.textContent = remote.slice(0, 2).toUpperCase();
  };

  const syncControlStates = () => {
    document.querySelectorAll('[data-call-mute]').forEach((button) => button.classList.toggle('is-active', state.muted));
    document.querySelectorAll('[data-call-hold]').forEach((button) => button.classList.toggle('is-active', state.held));
  };

  const setContactContext = (contact = null) => {
    if (!contactName || !contactCompany || !contactStatus || !contactHistory) return;

    if (!contact) {
      contactName.textContent = 'Sin contacto seleccionado';
      contactCompany.textContent = 'Cuando entre o marques una llamada mostraremos nombre, empresa y actividad reciente.';
      contactStatus.textContent = 'CRM';
      contactHistory.innerHTML = '<tr><td colspan="3"><span class="empty-copy">Sin historial cargado.</span></td></tr>';
      return;
    }

    contactName.textContent = contact.full_name || contact.organization || contact.mobile_phone || 'Contacto';
    contactCompany.textContent = [
      contact.account_name || contact.organization || '',
      contact.last_interaction_at ? `Ultima interaccion: ${contact.last_interaction_at}` : ''
    ].filter(Boolean).join(' / ');
    contactStatus.textContent = contact.linked_extension_status || 'Registrado';

    const rows = Array.isArray(contact.recent_calls) ? contact.recent_calls.slice(0, 5) : [];
    if (rows.length === 0) {
      contactHistory.innerHTML = '<tr><td colspan="3"><span class="empty-copy">Sin historial reciente para este contacto.</span></td></tr>';
      return;
    }

    contactHistory.innerHTML = rows.map((row) => {
      const when = row.start_time || row.started_at || '-';
      const direction = row.direction || '-';
      const duration = `${row.billsec || row.duration_seconds || row.duration || 0}s`;
      return `<tr><td>${when}</td><td>${direction}</td><td>${duration}</td></tr>`;
    }).join('');
  };

  const api = async (url, options = {}) => {
    const headers = options.headers || {};
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) {
      throw new Error(payload.error?.message || 'Request failed');
    }
    return payload.data;
  };

  const controlApi = async (endpoint, body) => api(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      company_id: selectedCompanyId(),
      origin_extension: selectedOrigin(),
      ...body
    })
  });

  const event = async (eventType, extra = {}) => {
    if (!state.token) return;
    await api('/api/v1/webrtc/events', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${state.token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ event_type: eventType, ...extra })
    }).catch(() => {});
  };

  const savePreferences = async () => {
    await api('/api/v1/webrtc/preferences', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        notifications_enabled: $('[data-notifications-toggle]')?.checked ? 'yes' : 'no',
        microphone_id: $('[data-audio-input]')?.value || '',
        speaker_id: $('[data-audio-output]')?.value || '',
        ringtone_volume: $('[data-ringtone-volume]')?.value || '70',
        default_presence: $('[data-presence-select]')?.value || 'available',
        extension: extensionSelect?.value || ''
      })
    }).catch(() => {});
  };

  const refreshStatus = async (number = '') => {
    const extension = extensionSelect?.value || '';
    const query = new URLSearchParams();
    if (extension) query.set('extension', extension);
    if (number) query.set('number', number);

    try {
      const data = await api(`/api/v1/webrtc/status?${query.toString()}`);
      if (data?.contact) setContactContext(data.contact);
      else if (number) setContactContext(null);
    } catch (error) {
      if (number) setContactContext(null);
    }
  };

  const loadBootstrap = async () => {
    const extension = extensionSelect?.value || '';
    const query = extension ? `?extension=${encodeURIComponent(extension)}` : '';
    const data = await api(`/api/v1/webrtc/config${query}`);
    state.token = data.token;
    state.preferences = data.preferences || {};
    state.sipDomain = String(data.sip.uri || '').split('@').pop() || null;
    return data;
  };

  const audioConstraints = (deviceId = '') => ({
    audio: {
      deviceId: deviceId ? { exact: deviceId } : undefined,
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true
    },
    video: false
  });

  const setupDevices = async () => {
    const input = $('[data-audio-input]');
    const output = $('[data-audio-output]');
    if (!navigator.mediaDevices?.enumerateDevices) return;

    try {
      await navigator.mediaDevices.getUserMedia(audioConstraints());
      const devices = await navigator.mediaDevices.enumerateDevices();
      const render = (select, kind) => {
        if (!select) return;
        select.innerHTML = '';
        devices.filter((device) => device.kind === kind).forEach((device) => {
          const option = document.createElement('option');
          option.value = device.deviceId;
          option.textContent = device.label || `${kind} ${select.length + 1}`;
          select.appendChild(option);
        });
      };
      render(input, 'audioinput');
      render(output, 'audiooutput');
      if (input && state.preferences.microphone_id) input.value = state.preferences.microphone_id;
      if (output && state.preferences.speaker_id) output.value = state.preferences.speaker_id;
    } catch (error) {
      setState('audio blocked');
    }
  };

  const attachRemoteAudio = (session) => {
    const handler = session.sessionDescriptionHandler;
    const peer = handler?.peerConnection;
    if (!peer || !remoteAudio) return;

    const stream = new MediaStream();
    peer.getReceivers().forEach((receiver) => {
      if (receiver.track) stream.addTrack(receiver.track);
    });
    remoteAudio.srcObject = stream;
    remoteAudio.volume = Number($('[data-output-volume]')?.value || 100) / 100;

    const output = $('[data-audio-output]');
    if (output?.value && typeof remoteAudio.setSinkId === 'function') {
      remoteAudio.setSinkId(output.value).catch(() => {});
    }
  };

  const startTimer = () => {
    state.callStartedAt = Date.now();
    clearInterval(state.timerId);
    state.timerId = setInterval(() => {
      const seconds = Math.floor((Date.now() - state.callStartedAt) / 1000);
      const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
      const ss = String(seconds % 60).padStart(2, '0');
      if (timer) timer.textContent = `${mm}:${ss}`;
    }, 500);
  };

  const stopTimer = () => {
    clearInterval(state.timerId);
    state.timerId = null;
    if (timer) timer.textContent = '00:00';
  };

  const startRing = () => {
    try {
      const AudioCtor = window.AudioContext || window.webkitAudioContext;
      state.ringContext = state.ringContext || new AudioCtor();
      state.ringOscillator = state.ringContext.createOscillator();
      const gain = state.ringContext.createGain();
      const volume = Number($('[data-ringtone-volume]')?.value || 70) / 100;
      state.ringOscillator.frequency.value = 440;
      gain.gain.value = Math.min(0.18, volume * 0.18);
      state.ringOscillator.connect(gain).connect(state.ringContext.destination);
      state.ringOscillator.start();
    } catch (error) {}
  };

  const stopRing = () => {
    try {
      state.ringOscillator?.stop();
    } catch (error) {}
    state.ringOscillator = null;
  };

  const showIncoming = (number) => {
    const popup = $('[data-incoming-popup]');
    const label = $('[data-incoming-number]');
    if (label) label.textContent = `Entrante: ${number || 'Desconocido'}`;
    if (popup) popup.hidden = false;
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Llamada entrante', { body: number || 'Desconocido' });
    }
    startRing();
  };

  const hideIncoming = () => {
    const popup = $('[data-incoming-popup]');
    if (popup) popup.hidden = true;
    stopRing();
  };

  const bindSession = (session, direction = 'outbound') => {
    state.session = session;
    const remoteNumber = session.remoteIdentity?.uri?.user || targetInput?.value || '';
    state.lastRemoteNumber = remoteNumber || null;
    state.currentCall = {
      call_id: session.id,
      remote_number: remoteNumber,
      state: direction === 'inbound' ? 'ringing' : 'starting',
      channel: state.currentCall?.channel || '',
      direction
    };
    updateCurrentCallCard(state.currentCall);
    refreshStatus(remoteNumber);
    event('call.created', { direction, remote_number: remoteNumber, call_id: session.id });

    session.stateChange.addListener((newState) => {
      const SessionState = SIP.SessionState || {};
      if (newState === SessionState.Established) {
        hideIncoming();
        setState('en llamada');
        startTimer();
        attachRemoteAudio(session);
        state.currentCall = { ...(state.currentCall || {}), call_id: session.id, remote_number: remoteNumber, state: 'active', direction };
        updateCurrentCallCard(state.currentCall);
        event('call.answered', { direction, remote_number: remoteNumber, call_id: session.id });
      }
      if (newState === SessionState.Terminated) {
        const duration = state.callStartedAt ? Math.floor((Date.now() - state.callStartedAt) / 1000) : 0;
        setState(state.registerer ? 'disponible' : 'desconectado');
        stopTimer();
        hideIncoming();
        event('call.ended', {
          direction,
          remote_number: remoteNumber,
          duration_seconds: duration,
          call_id: session.id,
          started_at: state.callStartedAt ? new Date(state.callStartedAt).toISOString().slice(0, 19).replace('T', ' ') : undefined
        });
        if (state.session === session) state.session = null;
        state.currentCall = null;
        updateCurrentCallCard(null);
        state.held = false;
        state.muted = false;
        syncControlStates();
        refreshStatus(state.lastRemoteNumber || '');
      }
    });
  };

  const register = async () => {
    if (!SIP?.UserAgent) {
      setState('sip.js missing');
      return;
    }

    const config = await loadBootstrap();
    await setupDevices();
    const uri = SIP.UserAgent.makeURI(config.sip.uri);
    const websocketUrl = resolveWebSocketUrl(config.sip.websocket_url || config.sip.websocket_path || '/ws');
    state.ua = new SIP.UserAgent({
      uri,
      displayName: config.sip.display_name,
      authorizationUsername: config.sip.authorization_username,
      authorizationPassword: config.sip.password,
      transportOptions: { server: websocketUrl },
      sessionDescriptionHandlerFactoryOptions: {
        constraints: audioConstraints($('[data-audio-input]')?.value || ''),
        peerConnectionConfiguration: {
          iceServers: [
            ...config.sip.stun_servers.map((url) => ({ urls: url })),
            ...config.sip.turn_servers.map((url) => ({ urls: url }))
          ]
        }
      }
    });

    state.ua.delegate = {
      onInvite(invitation) {
        bindSession(invitation, 'inbound');
        showIncoming(invitation.remoteIdentity?.uri?.user);
        setState('llamando');
        event('call.ringing', {
          direction: 'inbound',
          remote_number: invitation.remoteIdentity?.uri?.user || '',
          call_id: invitation.id
        });
      }
    };

    await state.ua.start();
    state.registerer = new SIP.Registerer(state.ua);
    await state.registerer.register();
    setState('disponible');
    event('register');
    refreshStatus();
  };

  const unregister = async () => {
    if (state.registerer) await state.registerer.unregister().catch(() => {});
    if (state.ua) await state.ua.stop().catch(() => {});
    state.registerer = null;
    state.ua = null;
    setState('desconectado');
    event('unregister');
    refreshStatus();
  };

  const call = async (number = '') => {
    if (!state.ua || !state.registerer) await register();
    const target = String(number || targetInput?.value || '').trim();
    if (!target) return;
    const domain = state.sipDomain || state.ua.configuration.uri.host;
    const targetUri = SIP.UserAgent.makeURI(`sip:${target}@${domain}`);
    const inviter = new SIP.Inviter(state.ua, targetUri);
    bindSession(inviter, 'outbound');
    setState('llamando');
    await inviter.invite();
  };

  const panelCall = async (number = '') => {
    const target = String(number || targetInput?.value || '').trim();
    const origin = selectedOrigin();
    if (!target || !origin) return;

    setState('llamando');

    try {
      const data = await api('/api/v1/pbx/originate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          company_id: selectedCompanyId(),
          origin_extension: origin,
          destination: target
        })
      });
      setState(data.queued ? 'llamando' : 'desconectado');
      refreshStatus(target);
    } catch (error) {
      setState('desconectado');
      window.alert(error.message || 'No se pudo originar la llamada PBX.');
    }
  };

  const hangup = async () => {
    const session = state.session;
    if (!session && state.currentCall?.channel) {
      await controlApi('/api/v1/calls/hangup', {
        channel: state.currentCall.channel,
        call_id: state.currentCall.call_id || ''
      }).catch(() => {});
      return;
    }
    if (!session) return;
    const SessionState = SIP.SessionState || {};
    if (session.state === SessionState.Initial || session.state === SessionState.Establishing) {
      const action = session.cancel?.() || session.reject?.();
      await action?.catch?.(() => {});
    } else {
      await session.bye?.().catch(() => {});
    }
    if (state.currentCall?.channel) {
      await controlApi('/api/v1/calls/hangup', {
        channel: state.currentCall.channel,
        call_id: session.id
      }).catch(() => {});
    }
  };

  const answer = async () => {
    if (!state.session?.accept) return;
    await state.session.accept();
  };

  const mute = () => {
    const peer = state.session?.sessionDescriptionHandler?.peerConnection;
    state.muted = !state.muted;
    peer?.getSenders().forEach((sender) => {
      if (sender.track?.kind === 'audio') sender.track.enabled = !state.muted;
    });
    setState(state.muted ? 'mute' : 'en llamada');
    syncControlStates();
  };

  const hold = async () => {
    if (!state.session) return;
    state.held = !state.held;
    const holdModifier = SIP.Web?.holdModifier;
    await state.session.invite({
      sessionDescriptionHandlerModifiers: state.held && holdModifier ? [holdModifier] : []
    }).catch(() => {});
    setState(state.held ? 'hold' : 'en llamada');
    syncControlStates();
    state.currentCall = { ...(state.currentCall || {}), state: state.held ? 'hold' : 'active' };
    updateCurrentCallCard(state.currentCall);
    await controlApi(state.held ? '/api/v1/calls/hold' : '/api/v1/calls/unhold', {
      call_id: state.session.id,
      channel: state.currentCall?.channel || ''
    }).catch(() => {});
    event(state.held ? 'call.hold' : 'call.unhold', {
      call_id: state.session.id,
      remote_number: state.lastRemoteNumber || ''
    });
  };

  const transfer = async (attended = false) => {
    const target = String($('[data-transfer-target]')?.value || '').trim();
    if (!target || (!state.session && !state.currentCall?.channel)) return;
    const domain = state.sipDomain || state.ua?.configuration?.uri?.host || window.location.hostname;
    const targetUri = state.ua ? SIP.UserAgent.makeURI(`sip:${target}@${domain}`) : null;
    if (attended) {
      if (state.ua && targetUri) {
        state.consultSession = new SIP.Inviter(state.ua, targetUri);
        bindSession(state.consultSession, 'outbound');
        await state.consultSession.invite();
      }
      await controlApi('/api/v1/calls/transfer', {
        channel: state.currentCall?.channel || '',
        destination: target,
        call_id: state.session?.id || state.currentCall?.call_id || '',
        mode: 'attended'
      }).catch(() => {});
      state.currentCall = { ...(state.currentCall || {}), state: 'transferring', destination: target };
      updateCurrentCallCard(state.currentCall);
      event('call.transfer', { transfer_target: target, mode: 'attended' });
      return;
    }
    if (state.session?.refer && targetUri) {
      await state.session.refer(targetUri).catch(() => {});
    }
    await controlApi('/api/v1/calls/transfer', {
      channel: state.currentCall?.channel || '',
      destination: target,
      call_id: state.session?.id || state.currentCall?.call_id || '',
      mode: 'blind'
    }).catch(() => {});
    state.currentCall = { ...(state.currentCall || {}), state: 'transferring', destination: target };
    updateCurrentCallCard(state.currentCall);
    event('call.transfer', { transfer_target: target, mode: 'blind' });
  };

  const park = async () => {
    if (!state.currentCall?.channel) return;
    const parkingLot = String($('[data-park-lot]')?.value || '').trim();
    const result = await controlApi('/api/v1/calls/park', {
      channel: state.currentCall.channel,
      call_id: state.session?.id || state.currentCall?.call_id || '',
      parking_lot: parkingLot
    });
    state.currentCall = { ...(state.currentCall || {}), state: 'parked' };
    updateCurrentCallCard(state.currentCall);
    event('call.park', {
      remote_number: state.lastRemoteNumber || '',
      call_id: state.session?.id || state.currentCall?.call_id || '',
      parking_lot: parkingLot
    });
    window.dispatchEvent(new CustomEvent('uc200:toast', { detail: { message: result.message || 'Llamada estacionada.' } }));
  };

  const pickup = async () => {
    const target = String($('[data-pickup-target]')?.value || targetInput?.value || '').trim();
    if (!target) return;
    const result = await controlApi('/api/v1/calls/pickup', {
      target_extension: target
    });
    setState(result.success ? 'llamando' : 'disponible');
    event('call.pickup', { target_extension: target });
  };

  const applyPhoneMode = (mode) => {
    root.classList.toggle('softphone-docked', mode === 'dock');
    root.classList.toggle('softphone-minimized', mode === 'min');
    document.querySelectorAll('[data-softphone-restore]').forEach((button) => {
      button.hidden = mode !== 'min';
    });
    window.localStorage.setItem('uc200_softphone_mode', mode);
  };

  applyPhoneMode(window.localStorage.getItem('uc200_softphone_mode') || 'normal');

  document.querySelectorAll('[data-softphone-dock]').forEach((button) => {
    button.addEventListener('click', () => {
      applyPhoneMode(root.classList.contains('softphone-docked') ? 'normal' : 'dock');
    });
  });
  document.querySelectorAll('[data-softphone-minimize]').forEach((button) => button.addEventListener('click', () => applyPhoneMode('min')));
  document.querySelectorAll('[data-softphone-restore]').forEach((button) => button.addEventListener('click', () => applyPhoneMode('normal')));

  document.querySelectorAll('[data-phone-register]').forEach((button) => button.addEventListener('click', register));
  document.querySelectorAll('[data-phone-unregister]').forEach((button) => button.addEventListener('click', unregister));
  document.querySelectorAll('[data-call-start]').forEach((button) => button.addEventListener('click', () => call()));
  document.querySelectorAll('[data-panel-call]').forEach((button) => button.addEventListener('click', () => panelCall()));
  document.querySelectorAll('[data-call-hangup]').forEach((button) => button.addEventListener('click', hangup));
  document.querySelectorAll('[data-call-answer]').forEach((button) => button.addEventListener('click', answer));
  document.querySelectorAll('[data-call-mute]').forEach((button) => button.addEventListener('click', mute));
  document.querySelectorAll('[data-call-hold]').forEach((button) => button.addEventListener('click', hold));
  document.querySelectorAll('[data-call-park], [data-call-park-inline]').forEach((button) => button.addEventListener('click', () => park().catch((error) => window.alert(error.message || 'No se pudo estacionar la llamada.'))));
  document.querySelectorAll('[data-call-pickup], [data-call-pickup-inline]').forEach((button) => button.addEventListener('click', () => pickup().catch((error) => window.alert(error.message || 'No se pudo recuperar la llamada.'))));
  document.querySelectorAll('[data-transfer-blind]').forEach((button) => button.addEventListener('click', () => transfer(false)));
  document.querySelectorAll('[data-transfer-attended]').forEach((button) => button.addEventListener('click', () => transfer(true)));

  document.querySelectorAll('[data-dtmf]').forEach((button) => {
    button.addEventListener('click', () => {
      const digit = button.dataset.dtmf;
      if (targetInput && !state.session) targetInput.value += digit;
      if (state.session?.dtmf) state.session.dtmf(digit);
    });
  });

  document.querySelectorAll('[data-call-number]').forEach((button) => {
    button.addEventListener('click', () => {
      const number = button.dataset.callNumber || '';
      if (targetInput) targetInput.value = number;
      call(number);
    });
  });

  $('[data-presence-select]')?.addEventListener('change', async (eventObject) => {
    await api('/api/v1/webrtc/presence', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: eventObject.target.value, extension: extensionSelect?.value || '' })
    }).catch(() => {});
  });

  $('[data-notifications-toggle]')?.addEventListener('change', async (eventObject) => {
    if ('Notification' in window && eventObject.target.checked && Notification.permission === 'default') {
      await Notification.requestPermission().catch(() => {});
    }
    await savePreferences();
  });

  ['data-audio-input', 'data-audio-output', 'data-ringtone-volume'].forEach((attribute) => {
    document.querySelector(`[${attribute}]`)?.addEventListener('change', savePreferences);
  });

  $('[data-output-volume]')?.addEventListener('input', (eventObject) => {
    if (remoteAudio) remoteAudio.volume = Number(eventObject.target.value || 100) / 100;
  });

  $('[data-audio-output]')?.addEventListener('change', () => {
    if (remoteAudio && typeof remoteAudio.setSinkId === 'function') {
      remoteAudio.setSinkId($('[data-audio-output]')?.value || '').catch(() => {});
    }
  });

  $('[data-audio-test]')?.addEventListener('click', async () => {
    const stream = await navigator.mediaDevices.getUserMedia(audioConstraints($('[data-audio-input]')?.value || '')).catch(() => null);
    if (!stream) return;
    const audio = new Audio();
    audio.srcObject = stream;
    audio.muted = true;
    await audio.play().catch(() => {});
    setTimeout(() => stream.getTracks().forEach((track) => track.stop()), 1200);
  });

  document.addEventListener('uc200:call_popup', (eventObject) => {
    const payload = eventObject.detail || {};
    if (!payload) return;

    if (payload.remote_number) {
      state.lastRemoteNumber = payload.remote_number;
      refreshStatus(payload.remote_number);
    }

    showIncoming(payload.contact_name || payload.remote_number || payload.destination || 'Llamada');
  });

  document.addEventListener('uc200:active_call', (eventObject) => {
    const payload = eventObject.detail || {};
    if (!payload) return;

    if (payload.remote_number) {
      state.lastRemoteNumber = payload.remote_number;
      refreshStatus(payload.remote_number);
    }

    state.currentCall = {
      ...(state.currentCall || {}),
      ...payload,
      channel: payload.channel || state.currentCall?.channel || '',
    };
    updateCurrentCallCard(state.currentCall);

    if (payload.state === 'active') {
      setState('en llamada');
    } else if (payload.state === 'ringing') {
      setState('llamando');
    } else if (payload.state === 'ended' && !state.session) {
      setState(state.registerer ? 'disponible' : 'desconectado');
      hideIncoming();
      state.currentCall = null;
      updateCurrentCallCard(null);
    }
  });

  document.addEventListener('uc200:extension_status', (eventObject) => {
    const payload = eventObject.detail || {};
    if (!payload || !selectedOrigin() || payload.endpoint_id !== selectedOrigin()) return;

    if (payload.status === 'offline' && !state.session) {
      setState('desconectado');
      return;
    }

    if (!state.session) {
      setState(payload.status === 'available' ? 'disponible' : payload.status);
    }
  });

  setupDevices();
  updateCurrentCallCard(null);
  syncControlStates();
  refreshStatus();
})();
