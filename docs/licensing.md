# Licensing Core

Modulo para administrar planes, features, limites y licencias por empresa.

## Rutas

- `GET /licensing`
- `GET /licensing/plans`
- `GET /licensing/features`
- `GET /licensing/licenses`

Cada recurso tiene rutas `create`, `store`, `edit`, `update` y `delete` bajo su prefijo.

## Tablas

- `feature_groups`
- `features`
- `plans`
- `plan_features`
- `company_licenses`
- `license_limits`

La tabla historica `licenses` queda sin eliminar por compatibilidad, pero el modulo nuevo usa `company_licenses`.

## Helpers

```php
hasFeature('core.dashboard');
licenseLimit('users');
```

`hasFeature()` retorna `true` para `super-admin`. Para usuarios tenant valida la licencia activa de su empresa.

`licenseLimit()` retorna el limite configurado en la licencia activa o `null` si no existe.

## Middleware

```php
$router->get('/ruta', [Controller::class, 'method'], ['auth', 'tenant', 'feature:core.dashboard']);
```

El middleware `feature:<slug>` permite `super-admin` y valida la licencia activa para usuarios de empresa.

## Cache

`LicenseService` guarda en sesion un cache basico de 5 minutos por empresa. El cache se limpia al crear, editar o eliminar licencias desde el modulo.

## Instalaciones existentes

Si la base ya fue creada antes de este modulo, ejecuta:

```bash
mysql -u uc200 -p uc200_core < database/updates/2026_05_15_licensing_core.sql
mysql -u uc200 -p uc200_core < database/seed.sql
```
