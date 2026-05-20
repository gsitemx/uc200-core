# AMI Gateway

Base inicial para controlar llamadas Asterisk desde UC200 Core.

## Alcance actual

- configuracion AMI por UI PBX
- almacenamiento seguro de credenciales
- login y ping a Asterisk Manager
- originate real
- hangup preparado
- listener de eventos preparado para worker futuro

## Settings usados

UC200 guarda estos valores en `settings`:

- `pbx.ami.enabled`
- `pbx.ami.host`
- `pbx.ami.port`
- `pbx.ami.username`
- `pbx.ami.password_encrypted`
- `pbx.ami.connect_timeout`
- `pbx.ami.originate_context`

Los valores pueden aplicarse de forma global o por tenant. Si existe configuracion del tenant, tiene prioridad sobre la global.

## Seguridad

- credenciales cifradas con `APP_KEY`
- fallback local si `APP_KEY` no existe
- permisos:
  - `pbx.originate`
  - `crm.call`
- auditoria de intentos originate
- rate limit por usuario/token

## API

- `GET /api/v1/pbx/ami/status`
- `POST /api/v1/pbx/originate`

Scopes API:

- `pbx_ami:read`
- `pbx_originate:write`

## Flujo originate

UC200 origina a la extension origen y, cuando la llamada es contestada, entrega el canal al contexto configurado para marcar el destino.

Prioridad de contexto:

1. `pbx.ami.originate_context`
2. `ps_endpoints.context` de la extension origen

Esto deja la logica de ruteo en el dialplan PBX actual, sin romper realtime PJSIP.

## Preparacion ARI

ARI no se implementa todavia. La base actual deja listo el punto de integracion para:

- control fino de canales
- eventos en tiempo real
- softphone popup
- sincronizacion CRM/call center

## Migracion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_15_ami_gateway.sql
```

## Asterisk manager.conf recomendado

```ini
[general]
enabled = yes
webenabled = no

[uc200]
secret = CAMBIAR_PASSWORD
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = system,call,log,verbose,command,agent,user,reporting
write = system,call,command,agent,user,originate,reporting
```

Despues de guardar:

```bash
asterisk -rx "manager reload"
```
