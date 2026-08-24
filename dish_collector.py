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


def _f(v):
    """Float or None — the dish omits fields rather than sending zero."""
    try:
        return float(v)
    except (TypeError, ValueError):
        return None


def store_outages(conn, outages):
    """Record the dish's own outage log, keyed on its nanosecond start.

    The ring buffer replays the same entries on every poll, so this relies on
    the primary key to ignore ones already stored rather than tracking state.
    """
    rows = []
    for o in outages:
        try:
            start_ns = int(o.get("startTimestampNs") or 0)
        except (TypeError, ValueError):
            continue
        if start_ns <= 0:
            continue
        rows.append((start_ns, start_ns // 1_000_000_000,
                     str(o.get("cause") or "UNKNOWN"),
                     round(int(o.get("durationNs") or 0) / 1e9, 3),
                     1 if o.get("didSwitch") else 0))
    if rows:
        conn.executemany(
            "INSERT OR IGNORE INTO outages "
            "(start_ns, ts, cause, duration_s, did_switch) VALUES (?,?,?,?,?)",
            rows)


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
                     ("sample_s", "INTEGER"),
                     # v3.5: power and the status fields the dish already sends
                     ("power_wh", "REAL"), ("power_w", "REAL"),
                     ("dish_power_w", "REAL"), ("router_power_w", "REAL"),
                     ("dl_limit", "TEXT"), ("ul_limit", "TEXT"),
                     ("cos", "TEXT"), ("heating", "INTEGER"),
                     ("obstructed_now", "INTEGER"), ("obstr_valid_s", "REAL"),
                     ("obstr_avg_dur_s", "REAL"), ("obstr_avg_int_s", "REAL"),
                     ("lat_p50", "REAL"), ("lat_p95", "REAL")):
        try:
            conn.execute(f"ALTER TABLE dish ADD COLUMN {col} {typ}")
        except sqlite3.OperationalError:
            pass

    # The dish keeps its own outage log with a cause and nanosecond timing.
    # That is authoritative, unlike anything inferred from drop rate, so it
    # gets its own table. start_ns is unique, which makes re-import a no-op:
    # the ring buffer reports the same outage on every poll until it ages out.
    conn.execute("""
        CREATE TABLE IF NOT EXISTS outages (
            start_ns INTEGER PRIMARY KEY,
            ts INTEGER NOT NULL,
            cause TEXT,
            duration_s REAL,
            did_switch INTEGER
        )
    """)
    conn.execute("CREATE INDEX IF NOT EXISTS idx_outages_ts ON outages(ts)")
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
        "bytes_down", "bytes_up", "sample_s",
        "power_wh", "power_w", "dish_power_w", "router_power_w",
        "dl_limit", "ul_limit", "cos", "heating",
        "obstructed_now", "obstr_valid_s", "obstr_avg_dur_s", "obstr_avg_int_s",
        "lat_p50", "lat_p95")}
    err = None

    try:
        st = grpc('{"get_status":{}}').get("dishGetStatus", {})
        row["uptime_s"] = int(st.get("deviceState", {}).get("uptimeS", 0))
        row["down_bps"] = float(st.get("downlinkThroughputBps", 0))
        row["up_bps"] = float(st.get("uplinkThroughputBps", 0))
        row["pop_latency_ms"] = round(float(st.get("popPingLatencyMs", 0)), 1)
        ob = st.get("obstructionStats", {}) or {}
        row["fraction_obstructed"] = float(ob.get("fractionObstructed", 0))
        row["obstructed_now"] = 1 if ob.get("currentlyObstructed") else 0
        row["obstr_valid_s"] = _f(ob.get("validS"))
        # Only meaningful once the dish says the running average is valid.
        if ob.get("avgProlongedObstructionValid"):
            row["obstr_avg_dur_s"] = _f(ob.get("avgProlongedObstructionDurationS"))
            row["obstr_avg_int_s"] = _f(ob.get("avgProlongedObstructionIntervalS"))

        # Whether the dish is being rate limited, and why. NO_LIMIT is the
        # normal case; anything else is the dish telling you it is throttled.
        row["dl_limit"] = st.get("dlBandwidthRestrictedReason") or None
        row["ul_limit"] = st.get("ulBandwidthRestrictedReason") or None
        row["cos"] = st.get("classOfService") or None

        ups = st.get("upsuStats", {}) or {}
        row["dish_power_w"] = _f(ups.get("dishPower"))
        row["router_power_w"] = _f(ups.get("routerPower"))
        row["gps_sats"] = int(st.get("gpsStats", {}).get("gpsSats", 0))
        row["eth_mbps"] = int(st.get("ethSpeedMbps", 0))
        al = st.get("alignmentStats", {})
        row["tilt"] = round(float(al.get("tiltAngleDeg", 0)), 1)
        row["azim"] = round(float(al.get("boresightAzimuthDeg", 0)), 1)
        row["elev"] = round(float(al.get("boresightElevationDeg", 0)), 1)
        row["sw"] = st.get("deviceInfo", {}).get("softwareVersion", "")
        active = [k for k, v in st.get("alerts", {}).items() if v]
        row["alerts"] = json.dumps(active) if active else None
        # Snow melt draws far more power than idle, so it is worth recording
        # separately rather than leaving it buried in the alert list.
        row["heating"] = 1 if st.get("alerts", {}).get("isHeating") else 0
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

            # power_in is watts sampled once a second, so the sum is
            # watt-seconds; divide by 3600 for watt-hours.
            pw = [float(v) for v in ring_tail(hi.get("powerIn", []), cur, span)
                  if v is not None]
            if pw:
                row["power_wh"] = round(sum(pw) / 3600.0, 4)
                row["power_w"] = round(pw[-1], 1)

            # Per-second latency gives a distribution; the status field only
            # ever gives one instant, which hides every spike between polls.
            lat = sorted(float(v) for v in
                         ring_tail(hi.get("popPingLatencyMs", []), cur, span)
                         if v is not None and float(v) > 0)
            if lat:
                row["lat_p50"] = round(lat[len(lat) // 2], 1)
                row["lat_p95"] = round(lat[min(len(lat) - 1,
                                               int(0.95 * len(lat)))], 1)

            store_outages(db, hi.get("outages") or [])
        except Exception:
            pass  # history optional

    db.execute(
        """INSERT INTO dish
           (ts, uptime_s, down_bps, up_bps, pop_latency_ms,
            drop_rate_60s, outage_s_60s, fraction_obstructed,
            gps_sats, eth_mbps, tilt, azim, elev, sw, alerts, error,
            bytes_down, bytes_up, sample_s,
            power_wh, power_w, dish_power_w, router_power_w,
            dl_limit, ul_limit, cos, heating,
            obstructed_now, obstr_valid_s, obstr_avg_dur_s, obstr_avg_int_s,
            lat_p50, lat_p95)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                   ?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
        (ts, row["uptime_s"], row["down_bps"], row["up_bps"],
         row["pop_latency_ms"], row["drop_rate_60s"], row["outage_s_60s"],
         row["fraction_obstructed"], row["gps_sats"], row["eth_mbps"],
         row["tilt"], row["azim"], row["elev"], row["sw"], row["alerts"], err,
         row["bytes_down"], row["bytes_up"], row["sample_s"],
         row["power_wh"], row["power_w"], row["dish_power_w"],
         row["router_power_w"], row["dl_limit"], row["ul_limit"],
         row["cos"], row["heating"], row["obstructed_now"],
         row["obstr_valid_s"], row["obstr_avg_dur_s"], row["obstr_avg_int_s"],
         row["lat_p50"], row["lat_p95"]),
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
             f"/{row['sample_s']}s" if row["sample_s"] else "")
          + (f" | {row['power_w']} W" if row["power_w"] else "")
          + (f" | LIMITED {row['dl_limit']}"
             if row["dl_limit"] and row["dl_limit"] != "NO_LIMIT" else ""))


if __name__ == "__main__":
    main()
