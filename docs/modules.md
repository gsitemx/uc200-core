# Modulos

La carpeta `modules/` queda reservada para funcionalidades desacopladas del nucleo.

Estructura sugerida por modulo:

```text
modules/example/
  Controllers/
  Models/
  Services/
  Views/
  routes.php
  module.php
```

Campos relevantes:

- `modules.slug`: identificador estable del modulo.
- `modules.is_enabled`: permite activar/desactivar modulos.
- `module_settings.company_id`: `NULL` para configuracion global o un `company_id` para configuracion por empresa.
- `module_settings.setting_value`: JSON para configuraciones flexibles.

El MVP puede iniciar con el modulo `core` y despues agregar modulos sin cambiar el router base.

## Sidebar

El layout base incluye un sidebar modular en `app/Views/layouts/app.php`. Por ahora muestra entradas reservadas para empresas, usuarios, roles, modulos y settings; cada modulo futuro puede registrar sus rutas y despues agregar su item al arreglo `$sidebarModules`.
