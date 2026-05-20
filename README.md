# UC200 Core

Base inicial para un sistema PHP 8.2 + MySQL/MariaDB con MVC simple, Composer, PDO, router basico, sesiones seguras, CSRF, login, roles, permisos, multiempresa, i18n basico y estructura modular.

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

## Multilenguaje

UC200 usa los helpers `__('clave')` y `lang()`. Los catalogos estan separados por dominio en `lang/es` y `lang/en`: `app.php`, `menu.php`, `auth.php`, `pbx.php`, `licensing.php` y `companies.php`. El idioma default es `es`, con fallback a `en`; puede configurarse por empresa (`companies.locale`) y por usuario (`users.locale`) sin traducir datos dinamicos.

## Enterprise UI

La UI usa un design system compacto con sidebar slim, topbar con breadcrumbs, quick actions, tablas densas, botones `sm/xs`, cards sobrias y dark mode preparado. Ver `docs/design-system.md`.

## Companies

El modulo Companies incluye CRUD de empresas, asignacion de licencia, creacion automatica de `ADMIN_EMPRESA`, settings por tenant, dashboard de empresa, middleware `tenant` y auditoria basica. Ver `docs/companies.md`.

## Licensing Core

El modulo Licensing Core incluye CRUD de planes, features y licencias, activacion de features por plan, limites por licencia, cache basico, expiracion automatica, middleware `feature:<slug>` y helpers `hasFeature()` / `licenseLimit()`. Ver `docs/licensing.md`.

## PBX Core

El modulo PBX Core prepara integracion Asterisk Realtime con PJSIP: extensiones SIP, transports, objetos `ps_endpoints`, `ps_auths`, `ps_aors`, contexto tenant, auditoria, estados SIP y estructura para presencia.

Incluye base inicial de seguridad SIP (`identify_by=auth_username`, fail2ban docs, recomendaciones iptables/rate limit, anonymous disabled), UX compacta para extensiones con formulario simple estilo 3CX, modal de configuracion de softphone y grabaciones con MixMonitor por tenant, metadata en `pbx_recordings`, UI con reproductor HTML5, filtros, busqueda y descarga. Ver `docs/pbx.md`.

## Identidad e integraciones

El login acepta email, extension asociada al tenant o username interno si existe. El email es dato principal en usuarios y extensiones, con preparacion de campos para Microsoft 365 y Google Workspace. El perfil incluye placeholders de integracion; OAuth real se implementara en fase posterior. Ver `docs/auth.md` y `docs/integrations.md`.

El sidebar usa iconos SVG inline locales, sin CDN externo, para evitar que se rendericen nombres como `grid`, `billing` o `settings`.

## CRM

El modulo CRM agrega contactos empresariales, cuentas/clientes, tags, notas, actividad, historial de llamadas relacionado y click-to-call simulado como preparacion para AMI/ARI. Tambien deja campos para Microsoft 365, Google Workspace, WhatsApp Omnichannel y API. Ver `docs/crm.md`.

## API REST

UC200 expone una primera API enterprise versionada en `/api/v1` con respuestas JSON estandarizadas, bearer tokens, expiracion, revocacion, scopes, restriccion opcional por IP, rate limiting, tenant isolation, paginacion, filtros, busqueda y ordenamiento.

Endpoints iniciales:

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

La administracion de tokens esta en `/settings/api-tokens`. Documentacion: `docs/api.md` y `docs/openapi.yaml`.

## Provisioning Engine

Modulo inicial para auto provisioning de telefonos Yealink, Grandstream, Fanvil, Poly y Cisco: inventario por MAC, modelo/firmware, ownership por tenant, plantillas, URLs de configuracion, BLF, phonebooks, preparacion RPS, solicitud de reboot y API read-only. Ver `docs/provisioning.md`.

## WebRTC Softphone

Plataforma inicial WebRTC con `transport-wss`, endpoints PJSIP compatibles con DTLS/ICE, softphone web basado en SIP.js, registro/unregister, controles de llamada, transfer, DTMF, presencia live, modo dock/minimizado, favoritos, llamadas recientes, seleccion de audio, control de volumen, rate limiting y eventos de sincronizacion. Ver `docs/webrtc.md`.

## Queue / Call Center

Modulo enterprise inicial para queues multi tenant, estrategias ringall/leastrecent/fewestcalls/random/rrmemory/linear, agentes dinamicos, login/logout, pause reasons, penalties, wallboard realtime, eventos, reportes SLA, abandonos, promedio de espera/talk time, overflow/failover e integracion futura con IVR/ring groups. Ver `docs/queues.md`.

## Billing + Reseller Platform

Modulo inicial enterprise para master admin, resellers multi nivel y tenants aislados. Incluye suscripciones, invoices, invoice items, taxes, balances, credits, usage tracking, addons de licenciamiento, automatizacion de suspension por vencimiento, preparacion Stripe/MercadoPago/PayPal, dashboard billing, gestion de tenants reseller y API read-only. Ver `docs/billing.md`.

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
- `APP_LOCALE=es`
- `APP_FALLBACK_LOCALE=en`
- `SESSION_SECURE=true`
- `APP_KEY` con un secreto aleatorio
- Permisos de escritura solo en `storage/`
