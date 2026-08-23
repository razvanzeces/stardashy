#!/usr/bin/env bash
# cfspeed installer — Raspberry Pi / Debian-like systems with systemd.
# Usage:  sudo ./install.sh            install/update to /opt/cfspeed
#         sudo CFSPEED_DEST=/srv/cfspeed ./install.sh
set -euo pipefail

DEST="${CFSPEED_DEST:-/opt/cfspeed}"
SRC="$(cd "$(dirname "$0")" && pwd)"
WEBGROUP="${CFSPEED_WEBGROUP:-www-data}"
UNITDIR="${CFSPEED_UNITDIR:-/etc/systemd/system}"

say(){ printf '\033[1m[cfspeed]\033[0m %s\n' "$*"; }
die(){ printf '\033[1;31m[cfspeed]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root: sudo ./install.sh"
command -v systemctl >/dev/null || die "systemd is required"
command -v python3 >/dev/null || die "python3 is required"

# ---------- dependencies ----------
if ! command -v grpcurl >/dev/null && [ ! -x /usr/local/bin/grpcurl ]; then
  say "WARNING: grpcurl not found — dish telemetry and satellite tracker"
  say "         will not work until you install it:"
  say "         https://github.com/fullstorydev/grpcurl/releases"
fi
if ! python3 -c 'import sgp4' 2>/dev/null; then
  say "installing python3-sgp4 for the satellite tracker…"
  pip3 install sgp4 --break-system-packages 2>/dev/null \
    || pip3 install sgp4 2>/dev/null \
    || say "WARNING: could not install sgp4 — sat_tracker.py will fail (pip3 install sgp4)"
fi
command -v php >/dev/null || say "WARNING: php not found — the dashboard needs php-fpm/php-cli + php-sqlite3"

# ---------- files ----------
# Running the installer from inside the install directory (a clone made
# straight into $DEST) is supported: there is simply nothing to copy.
if [ "$SRC" = "$DEST" ]; then
  say "running from ${DEST} — updating permissions only"
  chmod 0755 "$DEST"/*.py "$DEST"/install.sh "$DEST"/uninstall.sh 2>/dev/null || true
else
  say "installing to ${DEST}"
  mkdir -p "$DEST"
  for f in collector.py dish_collector.py sat_tracker.py alerter.py apply_config.py update.py icmp_collector.py; do
    install -m 0755 "$SRC/$f" "$DEST/$f"
  done
  install -m 0644 "$SRC/VERSION" "$DEST/VERSION"
  mkdir -p "$DEST/www"
  cp -r "$SRC/www/." "$DEST/www/"
  # Ship the installer, uninstaller and unit sources too, so the install
  # directory is self-contained and can be re-run or removed on its own.
  install -m 0755 "$SRC/install.sh" "$DEST/install.sh"
  install -m 0755 "$SRC/uninstall.sh" "$DEST/uninstall.sh"
  mkdir -p "$DEST/systemd"
  cp "$SRC"/systemd/*.service "$SRC"/systemd/*.timer "$SRC"/systemd/*.path "$DEST/systemd/"
fi

mkdir -p "$DEST/data"
if [ ! -f "$DEST/data/config.json" ]; then
  install -m 0664 "$SRC/config.example.json" "$DEST/data/config.json"
  say "created data/config.json from config.example.json"
fi

# web server (PHP) must be able to write config/auth/caches in data/
if getent group "$WEBGROUP" >/dev/null; then
  chgrp -R "$WEBGROUP" "$DEST/data"
  chmod 2775 "$DEST/data"
  chmod g+w "$DEST/data"/*.json 2>/dev/null || true
else
  say "WARNING: group '$WEBGROUP' not found — make $DEST/data writable by your web server"
fi

# ---------- vendored web assets (optional, keeps UI working offline) ----------
ASSETS="$DEST/www/assets"
mkdir -p "$ASSETS"
fetch(){ curl -fsSL --max-time 60 -o "$ASSETS/$1" "$2" && say "vendored $1" || say "skip $1 (offline?)"; }
if command -v curl >/dev/null; then
  [ -s "$ASSETS/chart.umd.min.js" ]      || fetch chart.umd.min.js      https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js
  [ -s "$ASSETS/satellite.min.js" ]      || fetch satellite.min.js      https://cdn.jsdelivr.net/npm/satellite.js@5.0.0/dist/satellite.min.js
  [ -s "$ASSETS/topojson-client.min.js" ] || fetch topojson-client.min.js https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js
  [ -s "$ASSETS/countries-110m.json" ]   || fetch countries-110m.json   https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json
fi

# ---------- systemd ----------
say "installing systemd units"
mkdir -p "$UNITDIR"
cp "$SRC"/systemd/*.service "$SRC"/systemd/*.timer "$SRC"/systemd/*.path "$UNITDIR"/
if [ "$DEST" != "/opt/cfspeed" ]; then
  # sed -i needs an argument on BSD/macOS; GNU sed rejects it, so branch
  if sed --version >/dev/null 2>&1; then
    sed -i    "s|/opt/cfspeed|$DEST|g" "$UNITDIR"/cfspeed*.service "$UNITDIR"/cfspeed*.path
  else
    sed -i '' "s|/opt/cfspeed|$DEST|g" "$UNITDIR"/cfspeed*.service "$UNITDIR"/cfspeed*.path
  fi
fi
systemctl daemon-reload
systemctl enable --now cfspeed.timer cfspeed-dish.timer cfspeed-sats.timer \
                       cfspeed-icmp.timer cfspeed-alerts.timer \
                       cfspeed-apply.path cfspeed-update.path

# apply configured intervals immediately
python3 "$DEST/apply_config.py" || true

say ""
say "done. next steps:"
say "  1. point your web server at $DEST/www (or test: php -S 0.0.0.0:8080 -t $DEST/www)"
say "  2. open the dashboard, go to SETTINGS — first visit asks you to set an admin password"
say "  3. set your dish location (or enable local location access in the Starlink app)"
say "  4. optionally add a Telegram bot token + chat id for alerts"
