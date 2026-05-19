# Auth e identidad

UC200 permite iniciar sesion con un identificador flexible:

- Email del usuario.
- Numero de extension asociado al email de la extension dentro del tenant.
- Username interno del usuario cuando exista.

La pantalla de login muestra `Email o extension`. El password sigue validandose contra `users.password_hash`, y se mantienen sesiones, CSRF y middleware actuales.

## Reglas de tenant

El login por extension no usa `auth_username`. UC200 busca una extension activa cuyo `extension_number` coincida y cuyo `email` pertenezca a un usuario activo de la misma empresa. Si la extension no puede resolverse de forma unica, el login falla para evitar acceso cruzado entre tenants.

El login por email valida un usuario activo. En la arquitectura actual el email de usuario se conserva como identificador estable de login.

## Auditoria

Los intentos de login registran eventos en `audit_logs`:

- `auth.login_success`
- `auth.login_failed`
- `auth.login_failed_inactive`

La auditoria guarda tipo de identificador y hash SHA-256 del valor usado, sin persistir el identificador en claro dentro del metadata.

## Microsoft 365 / Google OAuth

La base queda preparada para OAuth futuro con:

- `users.external_provider`
- `users.external_provider_id`
- `users.microsoft_user_id`
- `users.google_user_id`
- `users.sync_enabled`
- `users.last_synced_at`
- `user_external_accounts`

OAuth real, refresh tokens y consentimiento de Microsoft 365 / Google Workspace se implementaran en una fase posterior.
