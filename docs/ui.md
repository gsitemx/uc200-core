# UI Navigation

UC200 centraliza la navegacion lateral en `config/menu.php`. El layout ya no hardcodea modulos sueltos; renderiza secciones enterprise colapsables usando `app/Services/MenuService.php`.

## Estructura

Cada seccion del sidebar define:

- `id`
- `label_key`
- `icon`
- `default_open`
- `badge`
- `items`

Cada item soporta:

- `id`
- `label_key` o `label`
- `path`
- `icon`
- `roles`
- `permission`
- `feature`
- `company_only`
- `badge`
- `keywords`

## Permisos y features

`MenuService` filtra items segun:

- roles de sesion
- permisos reales (`PermissionService`)
- feature/licencia cuando el item define `feature`
- contexto de tenant cuando el item define `company_only`

`super-admin` ve todo por defecto. Los items sin ruta real no deben agregarse al menu visible.

## Badges

Los badges actuales soportan:

- `security_attacks`
- `extensions_online`
- `expired_licenses`
- `pending_invoices`

Se calculan de forma segura y opcional; si una tabla no existe, el badge se omite.

## Favoritos y colapso

El frontend guarda por usuario en `localStorage`:

- estado colapsado/expandido por seccion
- favoritos del sidebar

Claves usadas:

- `uc200-menu-section:{userId}:{sectionId}`
- `uc200-menu-favorites:{userId}`

## Agregar un modulo nuevo

1. Crear o confirmar la ruta web real.
2. Agregar labels en:
   - `lang/es/menu.php`
   - `lang/en/menu.php`
3. Registrar la entrada en `config/menu.php`
4. Si necesita badge, agregar la fuente en `MenuService::badges()`.
5. Si necesita icono nuevo, agregarlo en `render_icon()`.

## Notas

- El sidebar esta preparado para crecer hacia Omnichannel, Integraciones y Sistema avanzado sin volver a meter bloques hardcodeados en el layout.
- La busqueda del menu es local y filtra items/secciones sin refrescar la pagina.
