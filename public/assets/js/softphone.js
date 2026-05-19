(() => {
  const root = document.querySelector('[data-softphone-root]');
  if (!root) return;

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
    ringOscillator: null
  };

  const $ = (selector) => document.querySelector(selector);
  const statusBadge = $('[data-phone-state]');
  const extensionSelect = $('[data-extension-select]');
  const targetInput = $('[data-dial-target]');
  const remoteAudio = $('[data-remote-audio]');
  const timer = $('[data-call-timer]');

  const setState = (value) => {
    if (statusBadge) statusBadge.textContent = value;
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

  const loadBootstrap = async () => {
    const extension = extensionSelect?.value || '';
    const query = extension ? `?extension=${encodeURIComponent(extension)}` : '';
    const data = await api(`/api/v1/webrtc/bootstrap${query}`);
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
    if (label) label.textContent = `Incoming: ${number || 'Unknown'}`;
    if (popup) popup.hidden = false;
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Incoming call', { body: number || 'Unknown' });
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
    event('call.created', { direction, remote_number: remoteNumber, call_id: session.id });

    session.stateChange.addListener((newState) => {
      const SessionState = window.SIP?.SessionState || {};
      if (newState === SessionState.Established) {
        hideIncoming();
        setState('in call');
        startTimer();
        attachRemoteAudio(session);
        event('call.answered', { direction, remote_number: remoteNumber, call_id: session.id });
      }
      if (newState === SessionState.Terminated) {
        const duration = state.callStartedAt ? Math.floor((Date.now() - state.callStartedAt) / 1000) : 0;
        setState(state.registerer ? 'registered' : 'offline');
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
      }
    });
  };

  const register = async () => {
    if (!window.SIP) {
      setState('sip.js missing');
      return;
    }

    const config = await loadBootstrap();
    await setupDevices();
    const uri = window.SIP.UserAgent.makeURI(config.sip.uri);
    state.ua = new window.SIP.UserAgent({
      uri,
      displayName: config.sip.display_name,
      authorizationUsername: config.sip.authorization_username,
      authorizationPassword: config.sip.password,
      transportOptions: { server: config.sip.websocket_url },
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
        setState('ringing');
        event('call.ringing', {
          direction: 'inbound',
          remote_number: invitation.remoteIdentity?.uri?.user || '',
          call_id: invitation.id
        });
      }
    };

    await state.ua.start();
    state.registerer = new window.SIP.Registerer(state.ua);
    await state.registerer.register();
    setState('registered');
    event('register');
  };

  const unregister = async () => {
    if (state.registerer) await state.registerer.unregister().catch(() => {});
    if (state.ua) await state.ua.stop().catch(() => {});
    state.registerer = null;
    state.ua = null;
    setState('offline');
    event('unregister');
  };

  const call = async (number = '') => {
    if (!state.ua || !state.registerer) await register();
    const target = String(number || targetInput?.value || '').trim();
    if (!target) return;
    const domain = state.sipDomain || state.ua.configuration.uri.host;
    const targetUri = window.SIP.UserAgent.makeURI(`sip:${target}@${domain}`);
    const inviter = new window.SIP.Inviter(state.ua, targetUri);
    bindSession(inviter, 'outbound');
    setState('calling');
    await inviter.invite();
  };

  const hangup = async () => {
    const session = state.session;
    if (!session) return;
    const SessionState = window.SIP?.SessionState || {};
    if (session.state === SessionState.Initial || session.state === SessionState.Establishing) {
      const action = session.cancel?.() || session.reject?.();
      await action?.catch?.(() => {});
    } else {
      await session.bye?.().catch(() => {});
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
    setState(state.muted ? 'muted' : 'in call');
  };

  const hold = async () => {
    if (!state.session) return;
    state.held = !state.held;
    const holdModifier = window.SIP?.Web?.holdModifier;
    await state.session.invite({
      sessionDescriptionHandlerModifiers: state.held && holdModifier ? [holdModifier] : []
    }).catch(() => {});
    setState(state.held ? 'on hold' : 'in call');
  };

  const transfer = async (attended = false) => {
    const target = String($('[data-transfer-target]')?.value || '').trim();
    if (!target || !state.session) return;
    const domain = state.sipDomain || state.ua.configuration.uri.host;
    const targetUri = window.SIP.UserAgent.makeURI(`sip:${target}@${domain}`);
    if (attended) {
      state.consultSession = new window.SIP.Inviter(state.ua, targetUri);
      bindSession(state.consultSession, 'outbound');
      await state.consultSession.invite();
      return;
    }
    await state.session.refer(targetUri).catch(() => {});
    event('call.transfer', { transfer_target: target, mode: 'blind' });
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
  document.querySelectorAll('[data-call-hangup]').forEach((button) => button.addEventListener('click', hangup));
  document.querySelectorAll('[data-call-answer]').forEach((button) => button.addEventListener('click', answer));
  document.querySelectorAll('[data-call-mute]').forEach((button) => button.addEventListener('click', mute));
  document.querySelectorAll('[data-call-hold]').forEach((button) => button.addEventListener('click', hold));
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

  setupDevices();
})();
