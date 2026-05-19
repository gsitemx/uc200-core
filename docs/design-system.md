# UC200 Enterprise UI

UC200 usa un design system compacto orientado a operacion enterprise, inspirado en interfaces PBX tipo 3CX: alta densidad de informacion, jerarquia sobria, tablas legibles y acciones pequenas.

## Principios

- Sidebar slim y navegacion persistente.
- Topbar compacta con breadcrumbs, quick actions, selector de idioma y dark mode.
- Tablas densas con filtros inline, acciones `xs` y footer/paginacion visual.
- Cards sobrias, radio maximo de 8px y sombras discretas.
- Mobile first en comportamiento, desktop first en densidad.

## Componentes CSS

- `button ds-button`: variantes `primary`, `secondary`, `danger`; tamanos `sm` y `xs`.
- `ds-card`: contenedor base para paneles.
- `ds-table`: tabla compacta con headers pequenos.
- `badge ds-badge`: estados compactos, variante `on`.
- `metric-card ds-stat`: estadisticas KPI.
- `field ds-form-group`: grupo de formulario.
- `alert ds-alert`: mensajes `success` y `error`.
- `ds-modal`: contenedor base para modales futuros.

## Componentes PHP

Los parciales estan en `app/Views/components/`:

- `card.php`
- `table.php`
- `badge.php`
- `stat.php`
- `form-group.php`
- `modal.php`
- `alert.php`
- `button.php`

Se pueden usar con `view('components/button', [...], null)` cuando una pantalla necesite reutilizar HTML sin duplicar estilos.

## i18n

El helper principal es `__('clave')`; `lang()` es alias y sin parametro devuelve el idioma activo.

Catalogos:

- `lang/es/app.php`
- `lang/es/menu.php`
- `lang/es/auth.php`
- `lang/es/pbx.php`
- `lang/es/licensing.php`
- `lang/es/companies.php`
- Equivalentes en `lang/en/`

El idioma se resuelve en este orden:

1. `users.locale`
2. `companies.locale`
3. `APP_LOCALE`
4. fallback `APP_FALLBACK_LOCALE`, por defecto `en`

No se traducen datos dinamicos como nombres de empresas, extensiones, callers, callees, licencias o registros PBX.
