# Security Center

UC200 agrega un modulo web `Security Center` para operar seguridad PBX/SIP desde el panel, con foco inicial en:

- Fail2Ban
- monitoreo de ataques SIP en logs Asterisk
- firewall `iptables`
- whitelist / blacklist
- auditoria de acciones manuales

## Alcance inicial

- vista de estado de `fail2ban`
- jails activos
- IPs baneadas
- ban / unban manual
- reload / restart de `fail2ban`
- lectura de logs recientes
- parser de ataques SIP desde `/var/log/asterisk/messages`, `/var/log/asterisk/full` o `journalctl`
- bloqueo manual en `iptables`
- persistencia tentativa de reglas UC200

## Tablas

- `security_ip_whitelist`
- `security_ip_blacklist`
- `security_events`
- `security_bans`
- `security_settings`

## Roles

Solo `super-admin` y `admin-empresa` pueden entrar al modulo.  
Las acciones sensibles validan ademas permisos:

- `security.view`
- `security.manage`

## Comandos soportados

UC200 no ejecuta shell arbitrario. El backend solo arma comandos permitidos:

- `fail2ban-client status`
- `fail2ban-client status uc200-asterisk`
- `fail2ban-client set uc200-asterisk banip {ip}`
- `fail2ban-client set uc200-asterisk unbanip {ip}`
- `fail2ban-client reload`
- `systemctl restart fail2ban`
- `iptables` sobre la cadena `UC200_SECURITY`

La IP siempre se valida con `FILTER_VALIDATE_IP` antes de ejecutar cualquier accion.

## Scripts incluidos

```text
scripts/security/install_fail2ban.sh
scripts/security/install_uc200_asterisk_jail.sh
scripts/security/check_fail2ban.sh
```

Los scripts preparan:

- `/etc/fail2ban/filter.d/uc200-asterisk.conf`
- `/etc/fail2ban/jail.d/uc200-asterisk.local`

## Consideraciones operativas

- si PHP/Apache no tiene privilegios para `fail2ban-client`, `systemctl` o `iptables`, el panel mostrara error operativo
- se recomienda exponer estas capacidades solo en redes de administracion
- para persistencia de `iptables`, UC200 intenta usar:
  - `/etc/iptables/rules.v4`, o
  - `/etc/sysconfig/iptables`

## Sudoers recomendado

El panel normalmente corre como `www-data` o un usuario similar sin privilegios sobre:

- `/var/run/fail2ban/fail2ban.sock`
- `systemctl restart fail2ban`
- `iptables`

UC200 puede usar `sudo -n` para estos comandos. El ejemplo incluido esta en:

```text
scripts/security/uc200-security.sudoers
```

Despliegue sugerido:

```bash
cp scripts/security/uc200-security.sudoers /etc/sudoers.d/uc200-security
chmod 440 /etc/sudoers.d/uc200-security
visudo -cf /etc/sudoers.d/uc200-security
```

Si no quieres que UC200 use `sudo`, define:

```text
SECURITY_USE_SUDO=false
```

## Migracion

```bash
mysql -u uc200_user -p uc200_core < database/updates/2026_05_19_security_fail2ban_center.sql
```
