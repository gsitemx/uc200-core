# PBX Core

Modulo base para preparar integracion Asterisk Realtime con PJSIP.

## Alcance

- CRUD de extensiones SIP.
- Cada extension crea/edita `ps_endpoints`, `ps_auths` y `ps_aors`.
- CRUD de `ps_transports`.
- Separacion multiempresa por `company_id` y `context`.
- UUIDs, soft deletes y auditoria.
- Estado SIP preparado (`sip_status`).
- Presencia preparada (`presence_status`).
- Password SIP generado con `random_bytes()`.

No implementa llamadas, dialplan, AMI/ARI, WebRTC ni presencia en tiempo real.

## Tablas

- `ps_endpoints`
- `ps_auths`
- `ps_aors`
- `ps_transports`

Los nombres mantienen compatibilidad con Asterisk Realtime. Las columnas extra (`uuid`, `company_id`, `deleted_at`, estados internos) son para el core administrativo.

## Contexto tenant

Por defecto las extensiones usan:

```text
tenant_{company_id}
```

Ejemplo:

```text
tenant_1
```

Esto mantiene la separacion por empresa sin mezclar extensiones con el mismo numero.

## Rutas

- `GET /pbx`
- `GET /pbx/extensions`
- `GET /pbx/transports`
- `GET /pbx/realtime`

Las rutas de extensiones y transports incluyen `create`, `store`, `edit`, `update` y `delete`.

## Instalaciones existentes

```bash
mysql -u uc200 -p uc200_core < database/updates/2026_05_15_pbx_core.sql
mysql -u uc200 -p uc200_core < database/seed.sql
```
