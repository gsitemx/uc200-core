# Provisioning Engine

Modulo enterprise para auto provisioning multi-tenant con flujo simple para usuarios no tecnicos y modo avanzado restringido a `super-admin`.

## Flujo simple

El usuario normal solo necesita:

1. Seleccionar empresa
2. Seleccionar marca
3. Seleccionar modelo
4. Seleccionar extension
5. Guardar

UC200 genera automaticamente:

- URL de provisioning segura
- token por dispositivo
- archivo de configuracion con el formato correcto
- credenciales SIP seguras
- BLF automatico si el modelo lo soporta
- phonebook si el dispositivo tiene uno asignado

## Modo avanzado

El editor de plantillas personalizadas no aparece por defecto.

- Solo `super-admin` puede acceder a `/provisioning/templates/*`
- En la UI se muestra como `Modo avanzado`
- Debe usarse solo cuando el template interno no cubre un caso especial

Advertencia operativa:

- Las plantillas personalizadas pueden exponer detalles SIP si se usan mal
- El flujo recomendado para clientes y admins de tenant es el catalogo interno

## Catalogo precargado

Configurado centralmente en:

- `config/provisioning_devices.php`

Modelos incluidos:

### Yealink

- T31P
- T31G
- T33G
- T43U
- T46U
- T48U

### Grandstream

- GXP1625
- GXP2130
- GXP2170
- GRP2612
- GRP2614

### Fanvil

- X3U
- X4U
- X5U
- X6U

### Poly

- VVX 250
- VVX 350
- VVX 450

### Cisco SPA legacy

- SPA 303
- SPA 504G
- SPA 508G

## Defaults automaticos

UC200 aplica valores internos y acepta override por `settings`:

- `provisioning.sip_server`
- `provisioning.transport`
- `provisioning.timezone`
- `provisioning.ntp_server`
- `provisioning.language`
- `provisioning.interval`

Defaults base:

- `SIP server`: `PBX_SIP_SERVER` o dominio de `APP_URL`
- `Transport`: `UDP`
- `Timezone`: `America/Mazatlan`
- `NTP`: `pool.ntp.org`
- `Language`: `es`
- `Provision interval`: `1440`

## URL de provisionamiento

Cada telefono expone una URL con tenant y MAC:

```text
/provisioning/{tenant_uuid}/{mac}?token=xxxxxxxx
```

Compatibilidad legacy:

```text
/provisioning/config?mac=805EC0123456&secret=xxxxxxxx
```

El endpoint exige:

- MAC registrada
- tenant correcto
- `status=active`
- token correcto
- expiracion valida si el dispositivo tiene `token_expires_at`

## Formatos de archivo generados

UC200 genera nombre de archivo por marca/modelo:

- Yealink: `y0000000000xx.cfg`, `{mac}.cfg`
- Grandstream: `cfg{mac}.xml`
- Fanvil: `cfg{mac}.txt`
- Poly: `{mac}.cfg`
- Cisco SPA: `spa{mac}.cfg`

## Seguridad

- token por dispositivo
- expiracion opcional
- logs de descarga
- IP origen
- user-agent
- auditoria de regeneracion y acciones administrativas
- aislamiento por tenant

## BLF y phonebook

Si `blf_json` esta vacio y el modelo soporta BLF, UC200 intenta generar entradas automaticamente usando extensiones activas del tenant.

Los phonebooks se asignan por dispositivo y se publican usando el mismo token:

```text
/provisioning/{tenant_uuid}/{mac}/phonebook?token=xxxxxxxx
```

## UI

La interfaz ya incluye:

- `Asignar telefono`
- `Copiar URL provisioning`
- `Descargar archivo config`
- `Regenerar token`
- `Ver instrucciones`
- badges de capacidades:
  - BLF
  - Phonebook
  - Reboot
  - TLS preparado

## Troubleshooting

1. Verifica que la MAC este en formato limpio de 12 caracteres.
2. Verifica que el telefono este `active`.
3. Revisa que la URL tenga token valido.
4. Revisa `provisioning_download_logs` para IP, user-agent y status.
5. Si cambias token, la URL anterior deja de funcionar.
6. Si el proveedor necesita formato especial, usa modo avanzado solo con `super-admin`.

## Instalacion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_18_provisioning_engine.sql
mysql -u uc200_user -p uc200_core < database/updates/2026_05_20_device_provisioning.sql
mysql -u uc200_user -p uc200_core < database/updates/2026_05_20_provisioning_preloaded_templates.sql
```
