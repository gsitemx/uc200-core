#!/usr/bin/env bash
set -euo pipefail

if ! command -v fail2ban-client >/dev/null 2>&1; then
  echo "fail2ban-client not found"
  exit 1
fi

echo "== fail2ban service =="
systemctl is-active fail2ban || true
echo

echo "== fail2ban status =="
fail2ban-client status || true
echo

echo "== uc200-asterisk jail =="
fail2ban-client status uc200-asterisk || true
