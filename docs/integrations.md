# Integraciones

UC200 deja preparada la identidad para sincronizacion futura con Microsoft 365 y Google Workspace.

## Estado de vinculacion

En el perfil de usuario se muestra la seccion **Integraciones** con estos estados:

- No vinculado.
- Vinculado con Microsoft 365.
- Vinculado con Google Workspace.

Los botones **Conectar Microsoft 365** y **Conectar Google Workspace** son placeholders deshabilitados. No ejecutan OAuth todavia.

## Campos preparados

Usuarios y extensiones soportan:

- `email`
- `external_provider`
- `external_provider_id`
- `microsoft_user_id`
- `google_user_id`
- `sync_enabled`
- `last_synced_at`

La tabla `user_external_accounts` permite modelar multiples cuentas externas por usuario cuando se implemente OAuth real.

## Fase posterior

La siguiente fase debe agregar:

- Apps OAuth registradas en Microsoft Entra ID y Google Cloud.
- Callback seguro.
- Scopes minimos.
- Refresh tokens cifrados.
- Job de sincronizacion.
- Politicas por tenant para permitir o bloquear proveedores.
