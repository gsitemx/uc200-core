# Base de datos

Ejecuta los SQL en este orden:

```bash
mysql -u uc200 -p uc200_core < database/install.sql
mysql -u uc200 -p uc200_core < database/seed.sql
```

Si actualizas una instalacion existente que no tenia Licensing Core:

```bash
mysql -u uc200 -p uc200_core < database/updates/2026_05_15_licensing_core.sql
mysql -u uc200 -p uc200_core < database/seed.sql
```

`install.sql` crea solo la estructura de tablas.

`seed.sql` crea los datos iniciales:

- `superadmin@uc200.local` con contrasena `password`
- rol `super-admin`
- permisos base
- plan `Base`
- modulos `Core` y `Companies`
- settings globales iniciales

Para verificar:

```bash
mysql -u uc200 -p uc200_core -e "SELECT email, is_active FROM users WHERE email = 'superadmin@uc200.local';"
```

Si el usuario existe pero no acepta `password`, vuelve a ejecutar `seed.sql`; el seeder actualiza el hash inicial de `superadmin@uc200.local`:

```bash
mysql -u uc200 -p uc200_core < database/seed.sql
```
