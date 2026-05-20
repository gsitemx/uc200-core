# PBX Security

Esta guia resume la capa SIP/PBX de seguridad recomendada para UC200.

## Baseline recomendado

- `identify_by = auth_username`
- `allow_unauthenticated_options = false`
- `anonymous` deshabilitado
- `force_rport = yes`
- `rewrite_contact = yes`
- `rtp_symmetric = yes`
- `direct_media = no` cuando NAT lo requiera
- codecs minimos (`ulaw`, `alaw`) durante troubleshooting

## Fail2Ban

UC200 usa el jail sugerido `uc200-asterisk` y detecta:

- `Failed to authenticate`
- `No matching endpoint found`
- scanners SIP comunes
- multiples extensiones atacadas desde una misma IP
- `REGISTER flood`

## Firewall

La primera abstraccion soportada es `iptables` con la cadena:

```text
UC200_SECURITY
```

Objetivo:

- aislar reglas UC200 del resto del host
- preparar soporte futuro para `nftables` y `ufw`

## Operacion desde panel

Desde `Security Center` se puede:

- banear IP en Fail2Ban
- desbanear
- bloquear en firewall
- desbloquear
- agregar whitelist
- agregar blacklist
- recargar o reiniciar `fail2ban`

## Recomendacion de despliegue

- mantener AMI y Asterisk HTTP solo en localhost cuando sea posible
- usar proxy inverso para WSS `/ws`
- restringir el acceso al panel administrativo por IP o VPN si es viable
- revisar `scripts/security/` durante instalacion inicial
