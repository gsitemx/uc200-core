# Arquitectura

UC200 Core usa una arquitectura MVC simple y explicita.

## Flujo HTTP

1. El servidor web apunta a `public/index.php`.
2. `public/index.php` carga Composer, `.env`, configuracion y sesion.
3. `routes/web.php` registra rutas en `App\Core\Router`.
4. El router resuelve la ruta y ejecuta el controlador.
5. El controlador usa servicios/modelos y devuelve una vista.

## Capas

- `Controllers`: reciben requests y coordinan la respuesta.
- `Services`: contienen logica de aplicacion, autenticacion y permisos.
- `Models`: acceso simple a datos por entidad.
- `Core`: piezas de infraestructura liviana.
- `Views`: plantillas PHP sin motor externo.
- `modules`: espacio reservado para modulos funcionales del MVP.

## Modulos

Cada modulo futuro puede vivir en `modules/<module-name>` y registrar rutas, permisos, configuracion y vistas propias. La tabla `modules` mantiene el catalogo y `module_settings` permite configuracion global o por empresa.

## Roles y permisos

Los permisos son slugs globales como `core.users.manage`. Los roles pueden ser de sistema o pertenecer a una empresa. La relacion efectiva es:

`users -> user_roles -> roles -> role_permissions -> permissions`

## Autenticacion

El router acepta middleware por ruta:

- `auth`: exige usuario autenticado en sesion.
- `role:super-admin`: exige al menos un rol especifico.

El usuario `SUPERADMIN` no pertenece a ninguna empresa, por eso `users.company_id` permite `NULL`.

## Datos base

`database/install.sql` crea el esquema y `database/seed.sql` carga permisos core, rol `super-admin`, usuario `SUPERADMIN`, plan base, features iniciales, modulo core y settings globales.
