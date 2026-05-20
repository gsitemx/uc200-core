#!/usr/bin/env bash
set -euo pipefail

FILTER_PATH="/etc/fail2ban/filter.d/uc200-asterisk.conf"
JAIL_PATH="/etc/fail2ban/jail.d/uc200-asterisk.local"

mkdir -p /etc/fail2ban/filter.d /etc/fail2ban/jail.d

cat > "${FILTER_PATH}" <<'EOF'
[Definition]
failregex = ^\[[^]]+\]\s+NOTICE\[[0-9]+\]:.*Request 'REGISTER' from '.*' failed for '<HOST>:[0-9]+' .*Failed to authenticate$
            ^\[[^]]+\]\s+NOTICE\[[0-9]+\]:.*No matching endpoint found.*'<HOST>:[0-9]+'.*$
ignoreregex =
EOF

cat > "${JAIL_PATH}" <<'EOF'
[uc200-asterisk]
enabled = true
port = 5060,5061
filter = uc200-asterisk
logpath = /var/log/asterisk/messages
          /var/log/asterisk/full
backend = auto
maxretry = 10
findtime = 300
bantime = 3600
action = iptables-multiport[name=uc200-asterisk, port="5060,5061", protocol=udp]
EOF

echo "Installed ${FILTER_PATH}"
echo "Installed ${JAIL_PATH}"
echo "Run: systemctl restart fail2ban"
