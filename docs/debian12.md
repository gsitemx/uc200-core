# Debian 12

## Paquetes sugeridos

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server composer php8.2 php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## VirtualHost Apache

```apache
<VirtualHost *:80>
    ServerName uc200.local
    DocumentRoot /var/www/uc200-core/public

    <Directory /var/www/uc200-core/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/uc200-error.log
    CustomLog ${APACHE_LOG_DIR}/uc200-access.log combined
</VirtualHost>
```

## Permisos

```bash
sudo chown -R www-data:www-data /var/www/uc200-core/storage
sudo find /var/www/uc200-core/storage -type d -exec chmod 750 {} \;
sudo find /var/www/uc200-core/storage -type f -exec chmod 640 {} \;
```

Si Apache registra `session_start(): Failed to read session data: files`, revisa especificamente los archivos de sesion. Suele pasar cuando `storage/sessions/sess_*` fue creado por `root` u otro usuario:

```bash
sudo ls -la /var/www/uc200-core/storage/sessions
sudo chown -R www-data:www-data /var/www/uc200-core/storage/sessions
sudo find /var/www/uc200-core/storage/sessions -type d -exec chmod 750 {} \;
sudo find /var/www/uc200-core/storage/sessions -type f -exec chmod 640 {} \;
sudo systemctl reload apache2
```

Si usas PHP-FPM con otro usuario, sustituye `www-data:www-data` por el usuario/grupo real del pool.

## Base de datos

```bash
sudo mysql
CREATE DATABASE uc200_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uc200'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON uc200_core.* TO 'uc200'@'localhost';
FLUSH PRIVILEGES;
exit
```

Configura `/var/www/uc200-core/.env` con ese usuario. Si falta el archivo `.env`, la app usara el valor por defecto `DB_USERNAME=root` y MariaDB normalmente rechazara el acceso desde Apache:

```bash
cd /var/www/uc200-core
sudo cp .env.example .env
sudo nano .env
```

Valores esperados:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uc200_core
DB_USERNAME=uc200
DB_PASSWORD=change-this-password
DB_CHARSET=utf8mb4
```

Prueba el acceso antes de iniciar sesion en la app:

```bash
# Crea tablas
mysql -u uc200 -p uc200_core < database/install.sql
# Crea SUPERADMIN, roles, permisos, plan base y modulos
mysql -u uc200 -p uc200_core < database/seed.sql
```

Verifica que el usuario inicial exista:

```bash
mysql -u uc200 -p uc200_core -e "SELECT email, is_active FROM users WHERE email = 'superadmin@uc200.local';"
```

Para una instalacion que ya tenia tablas antes de Licensing Core:

```bash
mysql -u uc200 -p uc200_core < database/updates/2026_05_15_licensing_core.sql
mysql -u uc200 -p uc200_core < database/seed.sql
```
