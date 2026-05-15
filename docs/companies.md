# Modulo Companies

El modulo `Companies` administra la capa multiempresa inicial de UC200 Core.

## Alcance actual

- CRUD de empresas para usuarios `super-admin`.
- Creacion automatica del usuario `ADMIN_EMPRESA`.
- Creacion automatica del rol tenant `admin-empresa`.
- Asignacion de licencia por plan.
- Settings por empresa usando la tabla `settings`.
- Dashboard de empresa para `super-admin` y `admin-empresa`.
- Auditoria basica en `audit_logs`.
- Soft delete sobre `companies`.

## Rutas

- `GET /companies`
- `GET /companies/create`
- `POST /companies/store`
- `GET /companies/show?id={uuid}`
- `GET /companies/edit?id={uuid}`
- `POST /companies/update`
- `POST /companies/delete`
- `GET /companies/dashboard`

## Tenant

El middleware `tenant` permite pasar libremente a `super-admin`. Para usuarios de empresa valida que `company_id` exista, que la empresa no este eliminada y que su estado sea `active`.

El helper `currentCompany()` lee la empresa activa desde la sesion.
