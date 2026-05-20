# CRM y contactos empresariales

UC200 incluye un CRM basico multi tenant para centralizar contactos, cuentas/clientes, actividad y preparacion de integraciones.

## Contactos

Campos principales:

- Nombre.
- Empresa u organizacion.
- Puesto.
- Email.
- Telefono movil.
- Telefono oficina.
- DID relacionado opcional.
- Extension relacionada opcional.
- Tags.
- Notas.
- Origen: manual, Microsoft 365 futuro, Google Workspace futuro, WhatsApp futuro o API.

Cada contacto pertenece a un `company_id`. Los usuarios que no son super-admin solo ven contactos de su tenant.

## Cuentas CRM

Las cuentas agrupan contactos y representan clientes o empresas externas:

- Nombre comercial.
- Razon social.
- RFC / Tax ID.
- Email principal.
- Telefono principal.
- Sitio web.
- Direccion.
- Notas.

## Click-to-call

El boton **Llamar** registra una actividad tipo `call`, busca la extension del usuario logueado por email dentro del tenant y usa `PbxOriginateService`.

Con AMI habilitado:

- UC200 origina la llamada real desde la extension del usuario.
- el destino puede ser movil, oficina, DID o extension interna
- la accion queda auditada
- aplica rate limit por usuario

Sin AMI habilitado, UC200 responde con mensaje operativo y no rompe el flujo CRM.

## Historial

`crm_activities` guarda notas, llamadas, actividad de email, WhatsApp futuro y eventos de sistema.

`crm_call_logs` guarda historial PBX/CRM con:

- direccion
- src / dst
- start_time / answer_time / end_time
- duration / billsec
- disposition
- recording_path
- uniqueid / linkedid

El detalle del contacto muestra actividad, historial de llamadas y grabaciones relacionadas por telefono o DID cuando existen.

## PBX integrado

Cada contacto puede vincularse a:

- extension interna
- DID
- movil / telefono oficina

UC200 calcula el estado enlazado de la extension como:

- Disponible
- En llamada
- Registrado
- Offline

La capa `CallLogService` tambien prepara el lookup de caller ID inteligente por numero para popup futuro de softphone/web.

## Integraciones futuras

Contactos y cuentas incluyen:

- `external_provider`
- `external_id`
- `sync_enabled`
- `last_synced_at`

Estos campos preparan sincronizacion con Microsoft 365, Google Workspace, WhatsApp Omnichannel y API.

## API

Endpoints iniciales:

- `GET /api/v1/crm/contacts`
- `POST /api/v1/crm/contacts`
- `PUT /api/v1/crm/contacts`
- `DELETE /api/v1/crm/contacts`
- `GET /api/v1/crm/accounts`
- `POST /api/v1/crm/accounts`
- `PUT /api/v1/crm/accounts`
- `DELETE /api/v1/crm/accounts`
- `GET /api/v1/crm/activities`
- `GET /api/v1/crm/calls`
- `GET /api/v1/crm/contacts/lookup?company_id=1&number=525512345678`
- `GET /api/v1/pbx/ami/status`
- `POST /api/v1/pbx/originate`

Scopes:

- `crm_contacts:read`
- `crm_contacts:write`
- `crm_accounts:read`
- `crm_accounts:write`
- `crm_activities:read`
- `crm_calls:read`
- `pbx_ami:read`
- `pbx_originate:write`

La API respeta tenant isolation mediante el `company_id` asociado al token, salvo super-admin.

## Permisos

La migracion crea permisos base:

- `crm.view`
- `crm.create`
- `crm.update`
- `crm.delete`
- `crm.call`
