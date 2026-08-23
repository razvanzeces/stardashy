#!/usr/bin/env python3
"""cfspeed apply_config v2.0 — regenerate systemd timer overrides from
config.json and restart timers. Triggered automatically (as root) by
cfspeed-apply.path whenever the UI saves the config."""
import json
import os
import subprocess
import sys

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
CONFIG = os.path.join(BASE, "data", "config.json")
UNITDIR = os.environ.get("CFSPEED_UNITDIR") or "/etc/systemd/system"


def load():
    try:
        with open(CONFIG) as f:
            return json.load(f)
    except Exception:
        return {}


def clamp(v, lo, hi, dflt):
    try:
        v = int(v)
    except Exception:
        return dflt
    return max(lo, min(hi, v))


def write_override(unit, body):
    d = os.path.join(UNITDIR, f"{unit}.d")
    os.makedirs(d, exist_ok=True)
    with open(os.path.join(d, "override.conf"), "w") as f:
        f.write(body)


def main():
    cfg = load()
    iv = cfg.get("intervals", {})
    sp_min = clamp(iv.get("speedtest_min"), 2, 120, 30)
    dish_s = clamp(iv.get("dish_s"), 15, 3600, 60)
    sats_s = clamp(iv.get("sats_s"), 30, 3600, 60)

    write_override("cfspeed.timer",
        f"[Timer]\nOnCalendar=\nOnCalendar=*:0/{sp_min}\n")
    write_override("cfspeed-dish.timer",
        f"[Timer]\nOnCalendar=\nOnBootSec=20\nOnUnitActiveSec={dish_s}s\n")
    write_override("cfspeed-sats.timer",
        f"[Timer]\nOnCalendar=\nOnBootSec=40\nOnUnitActiveSec={sats_s}s\n")

    subprocess.run(["systemctl", "daemon-reload"], check=False)
    for t in ("cfspeed.timer", "cfspeed-dish.timer", "cfspeed-sats.timer"):
        subprocess.run(["systemctl", "restart", t], check=False)
    print(f"apply_config: speedtest {sp_min}min, dish {dish_s}s, sats {sats_s}s")


if __name__ == "__main__":
    sys.exit(main())
