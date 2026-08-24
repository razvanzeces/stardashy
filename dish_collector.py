#!/usr/bin/env python3
"""cfspeed dish collector v2.0 — Starlink dish gRPC -> SQLite.
Polls get_status + get_history via grpcurl. Run by systemd timer every minute.

Paths are relative to this script's directory (override with CFSPEED_HOME).
The dish endpoint and grpcurl binary can be overridden in data/config.json:
  "dish": {"target": "192.168.100.1:9200", "grpcurl": "/usr/local/bin/grpcurl"}
"""
import json
import shutil
import sqlite3
import subprocess
import time
import os
import sys

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
DB_PATH = os.path.join(DATA_DIR, "speed.db")
CONFIG_PATH = os.path.join(DATA_DIR, "config.json")

TARGET = "192.168.100.1:9200"
GRPCURL = None
METHOD = "SpaceX.API.Device.Device/Handle"

try:
    with open(CONFIG_PATH) as _f:
        _d = json.load(_f).get("dish", {})
    TARGET = _d.get("target") or TARGET
    GRPCURL = _d.get("grpcurl") or None
except Exception:
    pass
GRPCURL = GRPCURL or shutil.which("grpcurl") or "/usr/local/bin/grpcurl"


def grpc(payload: str):
    out = subprocess.run(
        [GRPCURL, "-plaintext", "-max-time", "10", "-d", payload, TARGET, METHOD],
        capture_output=True, text=True, timeout=15,
    )
    if out.returncode != 0:
        raise RuntimeError(out.stderr.strip()[:200] or "grpcurl failed")
    return json.loads(out.stdout)


def ring_tail(arr, current, n):
    """Last n samples from a gRPC ring buffer, oldest->newest."""
    if not arr:
        return []
    ln = len(arr)
    cur = int(current)
    n = min(n, ln)
    return [arr[(cur - n + i) % ln] for i in range(n)]


# The ring buffer is finite, so a long gap (reboot, stopped timer) can only be
# accounted for as far back as the buffer reaches. Anything older is lost
# rather than guessed at, and sample_s records what was actually covered.
MAX_SPAN_S = 900


def seconds_since_last_row(conn, ts):
    """How many seconds of history this run should account for."""
    try:
        prev = conn.execute(
            "SELECT MAX(ts) FROM dish WHERE ts < ?", (ts,)).fetchone()[0]
    except sqlite3.OperationalError:
        prev = None
    if not prev:
        return 60
    return max(1, min(MAX_SPAN_S, int(ts - prev)))


def ensure_db(conn):
    conn.execute("""
        CREATE TABLE IF NOT EXISTS dish (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL,
            uptime_s INTEGER,
            down_bps REAL,
            up_bps REAL,
            pop_latency_ms REAL,
            drop_rate_60s REAL,
            outage_s_60s INTEGER,
            fraction_obstructed REAL,
            gps_sats INTEGER,
            eth_mbps INTEGER,
            tilt REAL,
            azim REAL,
            elev REAL,
            sw TEXT,
            alerts TEXT,
            error TEXT
        )
    """)
    # Added after v3.3: integrated traffic for the window this row covers.
    for col, typ in (("bytes_down", "INTEGER"), ("bytes_up", "INTEGER"),
                     ("sample_s", "INTEGER")):
        try:
            conn.execute(f"ALTER TABLE dish ADD COLUMN {col} {typ}")
        except sqlite3.OperationalError:
            pass
    conn.execute("CREATE INDEX IF NOT EXISTS idx_dish_ts ON dish(ts)")
    conn.commit()


def main():
    os.makedirs(DATA_DIR, exist_ok=True)
    db = sqlite3.connect(DB_PATH)
    ensure_db(db)

    ts = int(time.time())
    row = {k: None for k in (
        "uptime_s", "down_bps", "up_bps", "pop_latency_ms",
        "drop_rate_60s", "outage_s_60s", "fraction_obstructed",
        "gps_sats", "eth_mbps", "tilt", "azim", "elev", "sw", "alerts",
        "bytes_down", "bytes_up", "sample_s")}
    err = None

    try:
        st = grpc('{"get_status":{}}').get("dishGetStatus", {})
        row["uptime_s"] = int(st.get("deviceState", {}).get("uptimeS", 0))
        row["down_bps"] = float(st.get("downlinkThroughputBps", 0))
        row["up_bps"] = float(st.get("uplinkThroughputBps", 0))
        row["pop_latency_ms"] = round(float(st.get("popPingLatencyMs", 0)), 1)
        row["fraction_obstructed"] = float(
            st.get("obstructionStats", {}).get("fractionObstructed", 0))
        row["gps_sats"] = int(st.get("gpsStats", {}).get("gpsSats", 0))
        row["eth_mbps"] = int(st.get("ethSpeedMbps", 0))
        al = st.get("alignmentStats", {})
        row["tilt"] = round(float(al.get("tiltAngleDeg", 0)), 1)
        row["azim"] = round(float(al.get("boresightAzimuthDeg", 0)), 1)
        row["elev"] = round(float(al.get("boresightElevationDeg", 0)), 1)
        row["sw"] = st.get("deviceInfo", {}).get("softwareVersion", "")
        active = [k for k, v in st.get("alerts", {}).items() if v]
        row["alerts"] = json.dumps(active) if active else None
    except Exception as e:
        err = f"status: {type(e).__name__}: {e}"

    if err is None:
        try:
            hi = grpc('{"get_history":{}}').get("dishGetHistory", {})
            cur = hi.get("current", 0)
            drops = ring_tail(hi.get("popPingDropRate", []), cur, 60)
            if drops:
                row["drop_rate_60s"] = round(sum(drops) / len(drops), 4)
                row["outage_s_60s"] = sum(1 for d in drops if d >= 1)

            # The dish keeps a per-second ring buffer of throughput. Summing
            # the samples covering the time since the previous run turns those
            # instantaneous rates into an actual byte count, which is the only
            # way to get real usage: sampling the rate once a minute would miss
            # everything between samples.
            span = seconds_since_last_row(db, ts)
            dn = ring_tail(hi.get("downlinkThroughputBps", []), cur, span)
            up = ring_tail(hi.get("uplinkThroughputBps", []), cur, span)
            if dn or up:
                n = max(len(dn), len(up))
                row["sample_s"] = n
                # one sample per second, bits -> bytes
                row["bytes_down"] = int(sum(max(0.0, float(v or 0)) for v in dn) / 8)
                row["bytes_up"] = int(sum(max(0.0, float(v or 0)) for v in up) / 8)
        except Exception:
            pass  # history optional

    db.execute(
        """INSERT INTO dish
           (ts, uptime_s, down_bps, up_bps, pop_latency_ms,
            drop_rate_60s, outage_s_60s, fraction_obstructed,
            gps_sats, eth_mbps, tilt, azim, elev, sw, alerts, error,
            bytes_down, bytes_up, sample_s)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
        (ts, row["uptime_s"], row["down_bps"], row["up_bps"],
         row["pop_latency_ms"], row["drop_rate_60s"], row["outage_s_60s"],
         row["fraction_obstructed"], row["gps_sats"], row["eth_mbps"],
         row["tilt"], row["azim"], row["elev"], row["sw"], row["alerts"], err,
         row["bytes_down"], row["bytes_up"], row["sample_s"]),
    )
    db.commit()
    db.close()

    if err:
        print(f"cfspeed-dish: FAILED — {err}", file=sys.stderr)
        sys.exit(1)
    print(f"cfspeed-dish: {round((row['down_bps'] or 0)/1e6,1)}v / "
          f"{round((row['up_bps'] or 0)/1e6,2)}^ Mbps | pop {row['pop_latency_ms']} ms | "
          f"drop60 {row['drop_rate_60s']} | obstr {round((row['fraction_obstructed'] or 0)*100,3)}%"
          + (f" | used {round(((row['bytes_down'] or 0)+(row['bytes_up'] or 0))/1e6,1)} MB"
             f"/{row['sample_s']}s" if row["sample_s"] else ""))


if __name__ == "__main__":
    main()
