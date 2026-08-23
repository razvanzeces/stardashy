#!/usr/bin/env python3
"""cfspeed collector v3.0 — Cloudflare speed test -> SQLite. Stdlib only.

Measures idle/loaded latency, download, upload (4 streams), ICMP ping,
then stores one row in data/speed.db. Broken-looking results (below
min_sane_mbps) are retried automatically and retries are recorded.

Paths are relative to this script's directory (override with CFSPEED_HOME),
so the stack runs from any checkout location.
"""
import http.client
import json
import re
import sqlite3
import statistics
import subprocess
import threading
import time
import os
import sys

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
DB_PATH = os.path.join(DATA_DIR, "speed.db")
CONFIG_PATH = os.path.join(DATA_DIR, "config.json")

HOST = "speed.cloudflare.com"

LATENCY_SAMPLES = 10
DOWN_SECONDS = 5.0
UP_SECONDS = 8.0
UP_STREAMS = 4
DOWN_REQ_BYTES = 50_000_000
UP_CHUNK = 1_000_000
LOADED_PROBE_INTERVAL = 0.4
PING_TARGET = "1.1.1.1"
PING_COUNT = 20
TIMEOUT = 30
UA = "cfspeed-collector/3.0"

MIN_SANE_MBPS = 5.0   # below this on either direction -> assume broken test
MAX_ATTEMPTS = 3      # initial + up to 2 retries
RETRY_WAIT_S = 15

try:  # UI-configurable overrides
    with open(CONFIG_PATH) as _f:
        _cfg = json.load(_f).get("speedtest", {})
    MIN_SANE_MBPS = float(_cfg.get("min_sane_mbps", MIN_SANE_MBPS))
    MAX_ATTEMPTS = int(_cfg.get("max_attempts", MAX_ATTEMPTS))
except Exception:
    pass

_dur = re.compile(r"dur=([0-9.]+)")


def new_conn():
    return http.client.HTTPSConnection(HOST, timeout=TIMEOUT)


def probe(conn):
    t0 = time.perf_counter()
    conn.request("GET", "/__down?bytes=0", headers={"User-Agent": UA})
    r = conn.getresponse()
    r.read()
    total_ms = (time.perf_counter() - t0) * 1000.0
    m = _dur.search(r.getheader("Server-Timing") or "")
    edge_ms = float(m.group(1)) if m else 0.0
    return max(total_ms - edge_ms, 0.0), r


def get_meta():
    meta = {}
    try:
        conn = new_conn()
        conn.request("GET", "/cdn-cgi/trace", headers={"User-Agent": UA})
        body = conn.getresponse().read().decode()
        conn.close()
        kv = dict(l.split("=", 1) for l in body.strip().splitlines() if "=" in l)
        meta = {"ip": kv.get("ip"), "colo": kv.get("colo"), "country": kv.get("loc")}
    except Exception:
        pass
    return meta


def robust_jitter(samples):
    if len(samples) < 2:
        return 0.0
    diffs = [abs(b - a) for a, b in zip(samples, samples[1:])]
    return round(statistics.median(diffs), 1)


def measure_latency():
    conn = new_conn()
    samples = []
    for i in range(LATENCY_SAMPLES + 1):
        ms, _ = probe(conn)
        if i > 0:
            samples.append(ms)
    conn.close()
    return round(min(samples), 1), robust_jitter(samples)


class LoadedLatencyProbe(threading.Thread):
    def __init__(self):
        super().__init__(daemon=True)
        self.samples = []
        self.stop_evt = threading.Event()

    def run(self):
        try:
            conn = new_conn()
            probe(conn)
            while not self.stop_evt.is_set():
                try:
                    ms, _ = probe(conn)
                    self.samples.append(ms)
                except Exception:
                    try:
                        conn.close()
                    except Exception:
                        pass
                    conn = new_conn()
                self.stop_evt.wait(LOADED_PROBE_INTERVAL)
            conn.close()
        except Exception:
            pass

    def result(self):
        return round(statistics.median(self.samples), 1) if self.samples else None


def measure_download():
    lp = LoadedLatencyProbe()
    lp.start()
    received = 0
    t0 = time.perf_counter()
    conn = new_conn()
    try:
        while time.perf_counter() - t0 < DOWN_SECONDS:
            conn.request("GET", f"/__down?bytes={DOWN_REQ_BYTES}",
                         headers={"User-Agent": UA})
            r = conn.getresponse()
            aborted = False
            while True:
                chunk = r.read(1 << 16)
                if not chunk:
                    break
                received += len(chunk)
                if time.perf_counter() - t0 >= DOWN_SECONDS:
                    aborted = True
                    break
            if aborted:
                conn.close()
                break
    finally:
        dt = time.perf_counter() - t0
        try:
            conn.close()
        except Exception:
            pass
        lp.stop_evt.set()
        lp.join(timeout=3)
    if received == 0:
        raise RuntimeError("download received 0 bytes")
    return round(received * 8 / dt / 1e6, 2), lp.result(), received


class UploadWorker(threading.Thread):
    def __init__(self, deadline):
        super().__init__(daemon=True)
        self.deadline = deadline
        self.sent = 0
        self.end = None

    def run(self):
        payload = b"\x00" * UP_CHUNK
        try:
            conn = new_conn()
            while time.perf_counter() < self.deadline:
                conn.request("POST", "/__up", body=payload,
                             headers={"User-Agent": UA,
                                      "Content-Type": "application/octet-stream"})
                conn.getresponse().read()
                self.sent += len(payload)
            conn.close()
        except Exception:
            pass
        self.end = time.perf_counter()


def measure_upload():
    lp = LoadedLatencyProbe()
    lp.start()
    t0 = time.perf_counter()
    deadline = t0 + UP_SECONDS
    workers = [UploadWorker(deadline) for _ in range(UP_STREAMS)]
    for w in workers:
        w.start()
    for w in workers:
        w.join(timeout=UP_SECONDS + TIMEOUT)
    lp.stop_evt.set()
    lp.join(timeout=3)
    sent = sum(w.sent for w in workers)
    ends = [w.end for w in workers if w.end]
    dt = (max(ends) if ends else time.perf_counter()) - t0
    if sent == 0:
        raise RuntimeError("upload sent 0 bytes")
    return round(sent * 8 / dt / 1e6, 2), lp.result(), sent


def measure_ping():
    try:
        out = subprocess.run(
            ["ping", "-c", str(PING_COUNT), "-i", "0.2", "-q", PING_TARGET],
            capture_output=True, text=True, timeout=30,
        ).stdout
        loss = rtt = None
        m = re.search(r"([\d.]+)% packet loss", out)
        if m:
            loss = float(m.group(1))
        m = re.search(r"= [\d.]+/([\d.]+)/", out)
        if m:
            rtt = round(float(m.group(1)), 1)
        return rtt, loss
    except Exception:
        return None, None


def ensure_db(conn):
    conn.execute("""
        CREATE TABLE IF NOT EXISTS results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL,
            latency_ms REAL, jitter_ms REAL,
            down_mbps REAL, up_mbps REAL,
            colo TEXT, client_ip TEXT, isp TEXT, error TEXT
        )
    """)
    for col, typ in [
        ("lat_down_ms", "REAL"), ("lat_up_ms", "REAL"),
        ("ping_ms", "REAL"), ("ping_loss", "REAL"),
        ("city", "TEXT"), ("country", "TEXT"), ("asn", "TEXT"),
        ("bytes_down", "INTEGER"), ("bytes_up", "INTEGER"),
        ("retries", "INTEGER"),
    ]:
        try:
            conn.execute(f"ALTER TABLE results ADD COLUMN {col} {typ}")
        except sqlite3.OperationalError:
            pass
    conn.execute("CREATE INDEX IF NOT EXISTS idx_results_ts ON results(ts)")
    conn.commit()


def run_attempt():
    """One full measurement pass. Returns dict or raises."""
    r = {}
    r["lat"], r["jit"] = measure_latency()
    r["down"], r["lat_d"], r["b_down"] = measure_download()
    r["up"], r["lat_u"], r["b_up"] = measure_upload()
    r["ping_ms"], r["ping_loss"] = measure_ping()
    return r


def main():
    os.makedirs(DATA_DIR, exist_ok=True)
    db = sqlite3.connect(DB_PATH)
    ensure_db(db)

    ts = int(time.time())
    meta = get_meta()
    result = None
    err = None
    attempts = 0

    for attempt in range(1, MAX_ATTEMPTS + 1):
        attempts = attempt
        try:
            r = run_attempt()
            result, err = r, None
            if r["down"] >= MIN_SANE_MBPS and r["up"] >= MIN_SANE_MBPS:
                break
            print(f"cfspeed: attempt {attempt} looks broken "
                  f"({r['down']}v / {r['up']}^ Mbps, threshold {MIN_SANE_MBPS}) "
                  f"— retrying", file=sys.stderr)
        except Exception as e:
            err = f"{type(e).__name__}: {e}"
            result = None
            print(f"cfspeed: attempt {attempt} failed — {err}", file=sys.stderr)
        if attempt < MAX_ATTEMPTS:
            time.sleep(RETRY_WAIT_S)

    retries = attempts - 1

    if result:
        db.execute(
            """INSERT INTO results
               (ts, latency_ms, jitter_ms, down_mbps, up_mbps,
                lat_down_ms, lat_up_ms, ping_ms, ping_loss,
                bytes_down, bytes_up, retries,
                colo, client_ip, isp, city, country, asn, error)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
            (ts, result["lat"], result["jit"], result["down"], result["up"],
             result["lat_d"], result["lat_u"], result["ping_ms"],
             result["ping_loss"], result["b_down"], result["b_up"], retries,
             meta.get("colo"), meta.get("ip"), None, None,
             meta.get("country"), None, None),
        )
    else:
        db.execute(
            """INSERT INTO results
               (ts, retries, colo, client_ip, country, error)
               VALUES (?,?,?,?,?,?)""",
            (ts, retries, meta.get("colo"), meta.get("ip"),
             meta.get("country"), err),
        )
    db.commit()
    db.close()

    if not result:
        print(f"cfspeed: FAILED after {attempts} attempts — {err}", file=sys.stderr)
        sys.exit(1)

    used_mb = round((result["b_down"] + result["b_up"]) / 1e6, 1)
    tag = f" | retries {retries}" if retries else ""
    low = (result["down"] < MIN_SANE_MBPS or result["up"] < MIN_SANE_MBPS)
    lowtag = " | STILL LOW after retries" if low else ""
    print(f"cfspeed: {result['down']}v / {result['up']}^ Mbps | "
          f"idle {result['lat']} ms jit {result['jit']} | "
          f"loaded {result['lat_d']}/{result['lat_u']} ms | "
          f"ping {result['ping_ms']} ms {result['ping_loss']}% | "
          f"{used_mb} MB used | {meta.get('colo', '?')}{tag}{lowtag}")


if __name__ == "__main__":
    main()
