#!/usr/bin/env python3
"""cfspeed ICMP health collector v1.0 — ping several anycast resolvers and
record reachability over time. Stdlib only.

Speed tests are expensive and run every 30 minutes; this is the cheap
counterpart that answers "is the link up right now, and to whom". Targets are
pinged concurrently so a dead one cannot delay the rest, and each run stores
one row per target.

Run by systemd timer. Interval, packet count and targets come from
data/config.json under "icmp".
"""
import json
import os
import re
import sqlite3
import subprocess
import sys
import threading
import time

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE, "data", "speed.db")
CONFIG = os.path.join(BASE, "data", "config.json")

MAX_TARGETS = 8          # keep a runaway config from hammering the link
PING_TIMEOUT_S = 2       # per packet
DEFAULT_COUNT = 5

DEFAULT_TARGETS = [
    {"label": "Cloudflare", "host": "1.1.1.1"},
    {"label": "Google", "host": "8.8.8.8"},
]

# A host must look like an IP or a hostname before it reaches the ping
# argument list. Nothing here is shell-interpreted, but a stray flag-looking
# value would still be read as an option by ping itself.
_HOST_RE = re.compile(r"^[A-Za-z0-9]([A-Za-z0-9.\-:]{0,253}[A-Za-z0-9])?$")


def load_config():
    try:
        with open(CONFIG) as f:
            return json.load(f)
    except Exception:
        return {}


def targets_from(cfg):
    ic = cfg.get("icmp") or {}
    raw = ic.get("targets")
    if not isinstance(raw, list) or not raw:
        raw = DEFAULT_TARGETS
    out = []
    for t in raw[:MAX_TARGETS]:
        if not isinstance(t, dict):
            continue
        host = str(t.get("host") or "").strip()
        if not _HOST_RE.match(host):
            continue
        label = str(t.get("label") or host).strip()[:40] or host
        out.append({"host": host, "label": label})
    return out


def ping(host, count):
    """Return (rtt_avg, rtt_min, rtt_max, mdev, loss_pct, error)."""
    try:
        p = subprocess.run(
            ["ping", "-n", "-c", str(count), "-i", "0.25",
             "-W", str(PING_TIMEOUT_S), "-q", host],
            capture_output=True, text=True,
            timeout=count * 0.25 + PING_TIMEOUT_S + 8,
        )
    except subprocess.TimeoutExpired:
        return None, None, None, None, 100.0, "timeout"
    except Exception as e:
        return None, None, None, None, None, f"{type(e).__name__}: {e}"

    out = p.stdout
    loss = None
    m = re.search(r"([\d.]+)% packet loss", out)
    if m:
        loss = float(m.group(1))

    stats = re.search(r"=\s*([\d.]+)/([\d.]+)/([\d.]+)/([\d.]+)", out)
    if stats:
        mn, avg, mx, md = (round(float(g), 2) for g in stats.groups())
        return avg, mn, mx, md, loss, None

    # No RTT line: either everything was lost, or ping failed outright.
    if loss is not None:
        return None, None, None, None, loss, None
    err = (p.stderr or out).strip().splitlines()
    return None, None, None, None, None, (err[-1][:150] if err else "ping failed")


def ensure_db(conn):
    conn.execute("""
        CREATE TABLE IF NOT EXISTS icmp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL,
            host TEXT NOT NULL,
            label TEXT,
            rtt_avg REAL,
            rtt_min REAL,
            rtt_max REAL,
            mdev REAL,
            loss REAL,
            error TEXT
        )
    """)
    conn.execute("CREATE INDEX IF NOT EXISTS idx_icmp_ts ON icmp(ts)")
    conn.execute("CREATE INDEX IF NOT EXISTS idx_icmp_host_ts ON icmp(host, ts)")
    conn.commit()


def main():
    cfg = load_config()
    ic = cfg.get("icmp") or {}
    if ic.get("enabled") is False:
        print("cfspeed-icmp: disabled in config")
        return 0

    try:
        count = max(1, min(20, int(ic.get("count", DEFAULT_COUNT))))
    except Exception:
        count = DEFAULT_COUNT

    targets = targets_from(cfg)
    if not targets:
        print("cfspeed-icmp: no valid targets configured", file=sys.stderr)
        return 1

    os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
    db = sqlite3.connect(DB_PATH, timeout=15)
    ensure_db(db)

    ts = int(time.time())
    results = {}

    def work(t):
        results[t["host"]] = ping(t["host"], count)

    threads = [threading.Thread(target=work, args=(t,), daemon=True)
               for t in targets]
    for th in threads:
        th.start()
    for th in threads:
        th.join(timeout=60)

    rows, summary = [], []
    for t in targets:
        avg, mn, mx, md, loss, err = results.get(
            t["host"], (None, None, None, None, None, "no result"))
        rows.append((ts, t["host"], t["label"], avg, mn, mx, md, loss, err))
        if err:
            summary.append(f"{t['label']} ERR")
        elif avg is None:
            summary.append(f"{t['label']} 100% loss")
        else:
            summary.append(f"{t['label']} {avg}ms"
                           + (f"/{loss}%" if loss else ""))

    db.executemany(
        """INSERT INTO icmp
           (ts, host, label, rtt_avg, rtt_min, rtt_max, mdev, loss, error)
           VALUES (?,?,?,?,?,?,?,?,?)""", rows)
    db.commit()
    db.close()

    print("cfspeed-icmp: " + " | ".join(summary))
    return 0


if __name__ == "__main__":
    sys.exit(main())
