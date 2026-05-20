# UC200 Core API

Base REST enterprise en `/api/v1`.

## Principios

- Respuestas JSON estandarizadas.
- Versionado por URL.
- Bearer tokens tipo Personal Access Token.
- Scopes por token.
- Expiracion y revocacion.
- Restriccion opcional por IP.
- Rate limiting por IP para login y por token para endpoints protegidos.
- Aislamiento por tenant mediante `company_id`.
- Paginacion, busqueda, filtros y ordenamiento en listados.

## Crear token

```bash
curl -X POST https://uc200.example.com/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret",
    "name": "ERP integration",
    "scopes": ["extensions:read", "recordings:read"],
    "expires_in_days": 30,
    "allowed_ips": "203.0.113.10"
  }'
```

Respuesta:

```json
{
  "success": true,
  "data": {
    "token_type": "Bearer",
    "access_token": "uc200_pat_...",
    "expires_at": "2026-06-17 00:00:00",
    "scopes": ["extensions:read", "recordings:read"]
  }
}
```

El token completo solo se muestra una vez.

## Usar token

```bash
curl https://uc200.example.com/api/v1/extensions?page=1&per_page=25&q=1001 \
  -H "Authorization: Bearer uc200_pat_..."
```

## Respuesta paginada

```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 0,
    "total_pages": 0,
    "sort": "e.extension_number",
    "direction": "asc"
  }
}
```

## Errores

```json
{
  "success": false,
  "error": {
    "code": "forbidden",
    "message": "Missing required scope: extensions:read"
  }
}
```

## Endpoints iniciales

- `POST /api/v1/auth/token`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/revoke`
- `DELETE /api/v1/auth/token`
- `GET /api/v1/companies`
- `GET /api/v1/users`
- `GET /api/v1/extensions`
- `GET /api/v1/ringgroups`
- `GET /api/v1/ivr`
- `GET /api/v1/trunks`
- `GET /api/v1/recordings`
- `GET /api/v1/provisioning/devices`
- `GET /api/v1/provisioning/templates`
- `GET /api/v1/provisioning/phonebooks`
- `GET /api/v1/crm/contacts`
- `GET /api/v1/crm/contacts/lookup`
- `POST /api/v1/crm/contacts`
- `PUT /api/v1/crm/contacts`
- `DELETE /api/v1/crm/contacts`
- `GET /api/v1/crm/accounts`
- `POST /api/v1/crm/accounts`
- `PUT /api/v1/crm/accounts`
- `DELETE /api/v1/crm/accounts`
- `GET /api/v1/crm/activities`
- `GET /api/v1/crm/calls`
- `GET /api/v1/pbx/ami/status`
- `POST /api/v1/pbx/originate`
- `GET /api/v1/security/fail2ban/status`
- `GET /api/v1/security/bans`
- `GET /api/v1/security/events`
- `POST /api/v1/security/ban`
- `POST /api/v1/security/unban`
- `GET /api/v1/realtime/token`
- `GET /api/v1/realtime/status`
- `GET /api/v1/queues`
- `GET /api/v1/queue-agents`
- `GET /api/v1/queue-events`
- `GET /api/v1/queue-metrics`
- `GET /api/v1/resellers`
- `GET /api/v1/billing/subscriptions`
- `GET /api/v1/billing/invoices`
- `GET /api/v1/billing/usage`
- `POST /api/v1/billing/webhook`
- `GET /api/v1/webrtc/bootstrap`
- `GET /api/v1/webrtc/config`
- `GET /api/v1/webrtc/token`
- `GET /api/v1/webrtc/status`
- `POST /api/v1/webrtc/preferences`
- `POST /api/v1/webrtc/presence`
- `POST /api/v1/webrtc/events`

`/api/v1/realtime/token` emite un JWT corto para Socket.IO bajo sesion UC200. `realtime/status` devuelve la configuracion activa y un health check ligero hacia el servicio Node interno.

## Scopes iniciales

- `*`
- `auth:read`
- `auth:write`
- `companies:read`
- `users:read`
- `extensions:read`
- `ringgroups:read`
- `ivr:read`
- `trunks:read`
- `recordings:read`
- `provisioning_devices:read`
- `provisioning_templates:read`
- `provisioning_phonebooks:read`
- `crm_contacts:read`
- `crm_contacts:write`
- `crm_accounts:read`
- `crm_accounts:write`
- `crm_activities:read`
- `crm_calls:read`
- `pbx_ami:read`
- `pbx_originate:write`
- `security_fail2ban:read`
- `security_bans:read`
- `security_events:read`
- `security_bans:write`
- `queues:read`
- `queue_agents:read`
- `queue_events:read`
- `queue_metrics:read`
- `resellers:read`
- `billing_subscriptions:read`
- `billing_invoices:read`
- `billing_usage:read`

## OpenAPI

El contrato inicial esta en:

```text
docs/openapi.yaml
```

La UI expone el archivo en:

```text
/docs/openapi.yaml
```

## Instalacion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_18_api_core.sql
```

Para AMI Gateway base:

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_15_ami_gateway.sql
```

Para Security Center:

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_19_security_fail2ban_center.sql
```
