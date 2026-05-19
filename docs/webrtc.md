# WebRTC + Web Softphone

UC200 incluye una primera plataforma WebRTC enterprise para operar un softphone web sobre Asterisk/PJSIP Realtime.

## Arquitectura

```mermaid
flowchart LR
    Browser["Browser Softphone (SIP.js)"] -->|"WSS / SIP"| Asterisk["Asterisk HTTP WebSocket"]
    Browser -->|"DTLS-SRTP / ICE"| RTP["Asterisk RTP"]
    UC200["UC200 Core"] -->|"Realtime DB"| Asterisk
    UC200 -->|"Session token / events"| Browser
```

## Componentes

- `transport-wss` en `ps_transports`.
- Columnas WebRTC en `ps_endpoints`:
  - `webrtc`
  - `media_encryption`
  - `dtls_auto_generate_cert`
  - `ice_support`
  - `use_avpf`
  - `rtcp_mux`
- Preferencias de usuario en `webphone_user_preferences`.
- Configuracion tenant/global en `webphone_settings`.
- Presencia en `pbx_presence_states`.
- Eventos y sincronizacion en `webphone_call_events`.
- Historial softphone en `webphone_recent_calls`.
- Token efimero en `webrtc_session_tokens`.
- Rate limit reutilizando `api_rate_limits` para bootstrap y eventos WebRTC.

## Flujo

1. Usuario abre `/softphone`.
2. UC200 entrega bootstrap con extension, auth SIP, WSS URL, STUN/TURN y token temporal.
3. SIP.js registra por `wss://host:8089/ws`.
4. Llamadas usan DTLS/SRTP e ICE.
5. El navegador reporta eventos a `/api/v1/webrtc/events`.
6. UC200 guarda recientes, presencia y preferencias.
7. La presencia se sincroniza como `available`, `ringing`, `busy` u `offline` segun el ciclo de llamada.

## Softphone Web

El softphone usa SIP.js y queda integrado en `/softphone` con:

- Registro y unregister por SIP sobre WebSocket seguro.
- Marcar extension/numero, recibir llamadas, colgar, mute, hold, DTMF y transfer.
- Modo dock y modo minimizado para operar como consola flotante.
- Seleccion de microfono/speaker, volumen de timbre, volumen de salida y prueba de audio.
- Constraints de audio con echo cancellation, noise suppression y auto gain control.
- Notificaciones del navegador para llamadas entrantes.
- Preparacion BLF/presencia con estados live por endpoint.

Los navegadores requieren HTTPS valido para microfono, notificaciones y WSS.

## Puertos firewall

Minimo recomendado:

```bash
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p tcp --dport 8089 -j ACCEPT
iptables -A INPUT -p udp --dport 10000:20000 -j ACCEPT
iptables -A INPUT -p udp --dport 3478 -j ACCEPT
iptables -A INPUT -p tcp --dport 5349 -j ACCEPT
```

Usa `443` para la app HTTPS, `8089/tcp` para Asterisk WSS, `10000:20000/udp` para RTP y `3478/5349` si despliegas TURN.

## Asterisk

Ejemplo base:

```ini
; http.conf
[general]
enabled=yes
bindaddr=0.0.0.0
bindport=8088
tlsenable=yes
tlsbindaddr=0.0.0.0:8089
tlscertfile=/etc/asterisk/keys/fullchain.pem
tlsprivatekey=/etc/asterisk/keys/privkey.pem

; rtp.conf
[general]
rtpstart=10000
rtpend=20000
icesupport=yes
stunaddr=stun.l.google.com:19302
```

El transporte realtime `transport-wss` queda creado por la migracion. Para cada extension, usa `Enable WebRTC` en `/softphone`.

## STUN/TURN

STUN publico sirve para pruebas. Para produccion usa TURN propio, por ejemplo coturn:

```bash
listening-port=3478
tls-listening-port=5349
fingerprint
lt-cred-mech
realm=pbx.example.com
user=uc200:strong-secret
cert=/etc/letsencrypt/live/pbx.example.com/fullchain.pem
pkey=/etc/letsencrypt/live/pbx.example.com/privkey.pem
```

Guarda las URLs en `webphone_settings.turn_urls` como JSON:

```json
["turn:pbx.example.com:3478?transport=udp","turns:pbx.example.com:5349?transport=tcp"]
```

## Compatibilidad

- Chrome y Edge: soporte completo esperado.
- Firefox: soporte WebRTC completo; salida de audio por `setSinkId` puede variar.
- Safari: soporte basico; algunas APIs de seleccion de speaker/notificaciones son limitadas.

## Troubleshooting

- Registro falla: valida certificado WSS, `http show status`, `pjsip show transport transport-wss`.
- Llamada sin audio: revisa `rtp set debug on`, puertos RTP, `external_media_address`, STUN/TURN.
- Error DTLS: confirma certificado, `media_encryption=dtls`, `dtls_auto_generate_cert=yes`.
- Solo una via de audio: activa TURN y verifica NAT/firewall.
- Browser bloquea microfono: revisar permisos del sitio y HTTPS valido.
- Eventos rechazados: confirma que el token `uc200_webrtc_*` no haya expirado y que no se haya excedido el rate limit.

## Instalacion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_18_webrtc_softphone.sql
asterisk -rx "pjsip reload"
asterisk -rx "module reload res_http_websocket.so"
```
