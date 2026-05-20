#!/usr/bin/env bash
set -euo pipefail

if command -v fail2ban-client >/dev/null 2>&1; then
  echo "fail2ban already installed"
  exit 0
fi

if command -v apt-get >/dev/null 2>&1; then
  apt-get update
  apt-get install -y fail2ban
  exit 0
fi

if command -v dnf >/dev/null 2>&1; then
  dnf install -y fail2ban
  systemctl enable --now fail2ban
  exit 0
fi

if command -v yum >/dev/null 2>&1; then
  yum install -y epel-release fail2ban
  systemctl enable --now fail2ban
  exit 0
fi

echo "Unsupported package manager. Install fail2ban manually."
exit 1
