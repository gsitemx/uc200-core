# Provisioning Engine

Modulo enterprise inicial para auto provisioning multi-tenant.

## Alcance

- Inventario de telefonos por tenant.
- MAC address normalizada.
- Vendor/model/firmware.
- Asignacion opcional a extension PBX.
- Plantillas por tenant/vendor/model.
- Generacion de configuracion por URL.
- BLF en JSON.
- Phonebooks por tenant.
- Preparacion RPS.
- Preparacion de solicitud de reboot.
- API read-only inicial.

Vendors preparados:

- Yealink
- Grandstream
- Fanvil
- Poly
- Cisco

## Tablas

- `provisioning_devices`
- `provisioning_templates`
- `provisioning_phonebooks`

Todas incluyen `company_id`, `uuid`, `status`, `created_at`, `updated_at`, `deleted_at` e indices por tenant.

## URL de provisionamiento

Cada telefono expone una URL con MAC y secreto:

```text
/provisioning/config?mac=805EC0123456&secret=xxxxxxxx
```

El endpoint es publico para que el telefono pueda descargar configuracion, pero exige:

- MAC registrada.
- `status=active`.
- `provisioning_secret` correcto.

## Variables de plantilla

Las plantillas aceptan variables simples:

```text
{{company_id}}
{{company_name}}
{{mac}}
{{vendor}}
{{model}}
{{firmware}}
{{extension}}
{{sip_username}}
{{auth_username}}
{{auth_password}}
{{sip_server}}
{{display_name}}
{{blf_json}}
```

Si no existe plantilla personalizada, UC200 genera un template default por vendor.

## BLF

`blf_json` queda preparado para mapear teclas:

```json
[
  {"key": 1, "type": "blf", "label": "1002", "value": "1002"},
  {"key": 2, "type": "speed_dial", "label": "Soporte", "value": "1000"}
]
```

La expansion vendor-specific de BLF se hara desde las plantillas.

## RPS

`rps_enabled=yes` prepara el dispositivo para integraciones futuras con Remote Provisioning Server de fabricantes. En esta fase no llama APIs externas; conserva el estado y el secreto de provisionamiento.

## Reboot

La accion `Reboot` marca `reboot_requested_at`. El envio real de SIP NOTIFY/vendor API queda preparado para el siguiente paso.

## API

Endpoints:

- `GET /api/v1/provisioning/devices`
- `GET /api/v1/provisioning/templates`
- `GET /api/v1/provisioning/phonebooks`

Scopes:

- `provisioning_devices:read`
- `provisioning_templates:read`
- `provisioning_phonebooks:read`

## Instalacion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_18_provisioning_engine.sql
```
