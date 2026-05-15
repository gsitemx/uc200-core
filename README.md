# UC200 Core

Base inicial para un sistema PHP 8.2 + MySQL/MariaDB con MVC simple, Composer, PDO, router basico, sesiones seguras, CSRF, login, roles, permisos, multiempresa y estructura modular.

No incluye Asterisk todavia. Esta base queda lista para iniciar el MVP.

## Requisitos

- Debian 12
- PHP 8.2
- Extensiones PHP: `pdo`, `pdo_mysql`
- Composer
- MySQL 8 o MariaDB 10.11+
- Apache con `mod_rewrite` o Nginx apuntando a `public/`

## Instalacion rapida

```bash
composer install
cp .env.example .env
mysql -u root -p -e "CREATE DATABASE uc200_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Crea tablas
mysql -u root -p uc200_core < database/install.sql
# Crea usuario inicial, roles, permisos, plan base y modulos
mysql -u root -p uc200_core < database/seed.sql
composer serve
```

Abre `http://127.0.0.1:8080`.

Usuario inicial:

- Correo: `superadmin@uc200.local`
- Contrasena: `password`

El usuario inicial no se crea con `install.sql`; se crea al ejecutar `database/seed.sql`.

Cambia esta contrasena antes de usar el sistema fuera de desarrollo.

## Estructura

```text
app/
  Controllers/   Controladores MVC
  Core/          Router, request, response, session, CSRF, PDO y vistas
  Middleware/    Middleware base
  Models/        Modelos simples
  Services/      Logica de aplicacion
  Views/         Plantillas PHP
config/          Configuracion de app y base de datos
database/        SQL inicial
docs/            Documentacion tecnica
modules/         Punto de entrada para modulos futuros
public/          Document root
routes/          Rutas web
storage/         Cache, logs y sesiones
tests/           Pruebas futuras
```

Los modulos pueden agregar un archivo `modules/<slug>/routes.php`; el bootstrap lo carga automaticamente despues de las rutas base.

## Login

El login esta conectado a la tabla `users`, carga roles en sesion y redirige al dashboard. Las rutas pueden protegerse con middleware:

```php
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'role:super-admin']);
```

Las contrasenas deben crearse con `password_hash()`.

## Companies

El modulo Companies incluye CRUD de empresas, asignacion de licencia, creacion automatica de `ADMIN_EMPRESA`, settings por tenant, dashboard de empresa, middleware `tenant` y auditoria basica. Ver `docs/companies.md`.

## Licensing Core

El modulo Licensing Core incluye CRUD de planes, features y licencias, activacion de features por plan, limites por licencia, cache basico, expiracion automatica, middleware `feature:<slug>` y helpers `hasFeature()` / `licenseLimit()`. Ver `docs/licensing.md`.

## PBX Core

El modulo PBX Core prepara integracion Asterisk Realtime con PJSIP: extensiones SIP, transports, objetos `ps_endpoints`, `ps_auths`, `ps_aors`, contexto tenant, auditoria, estados SIP y estructura para presencia. No implementa llamadas ni WebRTC. Ver `docs/pbx.md`.

Ejemplo para generar un hash:

```bash
php -r "echo password_hash('secret', PASSWORD_DEFAULT) . PHP_EOL;"
```

## Seguridad base

- Cookies de sesion `HttpOnly`
- `session.use_strict_mode`
- Regeneracion de sesion al iniciar sesion
- CSRF automatico en metodos no `GET`
- Middleware `auth`
- Middleware `role:<slug>`
- PDO con prepared statements y emulacion desactivada
- Document root separado en `public/`
- Soft deletes con `deleted_at` en entidades principales
- UUIDs en entidades principales

## Produccion

En produccion configura:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE=true`
- `APP_KEY` con un secreto aleatorio
- Permisos de escritura solo en `storage/`
