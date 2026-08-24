#!/usr/bin/env bash
# cfspeed uninstaller — stops timers and removes units. Keeps /opt/cfspeed
# (your data) unless you pass --purge.
set -euo pipefail
DEST="${CFSPEED_DEST:-/opt/cfspeed}"
[ "$(id -u)" -eq 0 ] || { echo "run as root"; exit 1; }

systemctl disable --now cfspeed.timer cfspeed-dish.timer cfspeed-sats.timer \
  cfspeed-icmp.timer cfspeed-alerts.timer cfspeed-apply.path \
  cfspeed-update.path 2>/dev/null || true
rm -f /etc/systemd/system/cfspeed*.service /etc/systemd/system/cfspeed*.timer \
      /etc/systemd/system/cfspeed*.path
rm -rf /etc/systemd/system/cfspeed*.d
systemctl daemon-reload

if [ "${1:-}" = "--purge" ]; then
  rm -rf "$DEST"
  userdel cfspeed 2>/dev/null || true
  echo "removed $DEST"
else
  echo "units removed; data kept in $DEST (use --purge to delete)"
fi
