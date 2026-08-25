#!/usr/bin/env python3
"""cfspeed satellite tracker v2.0 — infer overhead Starlink satellites.

The dish gRPC API does not expose the serving satellite ID, so we infer:
  1. read dish boresight az/el via grpcurl get_status
  2. propagate the public Starlink TLE set (CelesTrak) with SGP4
  3. list satellites above the elevation mask at the dish location
  4. best candidate = smallest angular separation from the dish boresight

Requires: pip install sgp4  (on Debian/Raspberry Pi OS: --break-system-packages)
Run by systemd timer every minute.

Location comes from data/config.json:
  "location": {"lat": 45.0, "lon": 25.0, "alt_m": 100, "publish_precision": 2}
If lat/lon are null the tracker asks the dish itself via gRPC get_location
(needs "allow access on local network" enabled in the Starlink app under
Advanced -> Debug data). publish_precision controls how many decimals of the
observer position are written to the public sky.json (2 ≈ 1 km).
"""
import json
import math
import os
import shutil
import sqlite3
import subprocess
import sys
import time
import urllib.request

from sgp4.api import Satrec, jday

# --- config ---------------------------------------------------------------
BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
DB_PATH = os.path.join(DATA_DIR, "speed.db")
CONFIG_PATH = os.path.join(DATA_DIR, "config.json")
TLE_PATH = os.path.join(DATA_DIR, "starlink.tle")
TLE_URL = "https://celestrak.org/NORAD/elements/gp.php?GROUP=starlink&FORMAT=tle"
TLE_MAX_AGE_S = 12 * 3600

# Satellite tracking follows the first configured dish. Two dishes at one
# site see the same sky, and two at different sites need their own boresight
# and location — that is a separate view, not an average, so it is left for
# a later version rather than silently mixing them.
TARGET = "192.168.100.1:9200"
GRPCURL = None
METHOD = "SpaceX.API.Device.Device/Handle"

EL_MASK_DEG = 25.0   # only count satellites above this elevation
TOP_N = 3            # store the N closest candidates
# --------------------------------------------------------------------------

try:
    with open(CONFIG_PATH) as _f:
        _cfg = json.load(_f)
except Exception:
    _cfg = {}
_d = _cfg.get("dish", {}) or {}
TARGET = _d.get("target") or TARGET
GRPCURL = (_d.get("grpcurl") or None) or shutil.which("grpcurl") \
    or "/usr/local/bin/grpcurl"

WGS84_A = 6378.137          # km
WGS84_F = 1 / 298.257223563
WGS84_E2 = WGS84_F * (2 - WGS84_F)


def grpc(payload: str):
    out = subprocess.run(
        [GRPCURL, "-plaintext", "-max-time", "5", "-d", payload, TARGET, METHOD],
        capture_output=True, text=True, timeout=10,
    )
    if out.returncode != 0:
        raise RuntimeError(out.stderr.strip()[:200] or "grpcurl failed")
    return json.loads(out.stdout)


def get_boresight():
    st = grpc('{"get_status":{}}').get("dishGetStatus", {})
    al = st.get("alignmentStats", {})
    az = float(al.get("desiredBoresightAzimuthDeg",
                      al.get("boresightAzimuthDeg", 0)))
    el = float(al.get("desiredBoresightElevationDeg",
                      al.get("boresightElevationDeg", 90)))
    return az, el


def get_location():
    loc = _cfg.get("location", {}) or {}
    lat, lon = loc.get("lat"), loc.get("lon")
    if lat is not None and lon is not None:
        return float(lat), float(lon), float(loc.get("alt_m") or 0) / 1000.0
    j = grpc('{"get_location":{}}')
    lla = j.get("getLocation", {}).get("lla", {})
    if "lat" not in lla:
        raise RuntimeError(
            "location unavailable — set location.lat/lon in Settings (or "
            "data/config.json), or enable local location access in the "
            "Starlink app (Advanced -> Debug data)")
    return float(lla["lat"]), float(lla["lon"]), float(lla.get("alt", 0)) / 1000.0


def refresh_tle():
    fresh = (os.path.isfile(TLE_PATH)
             and time.time() - os.path.getmtime(TLE_PATH) < TLE_MAX_AGE_S)
    if fresh:
        return
    try:
        req = urllib.request.Request(TLE_URL, headers={"User-Agent": "cfspeed-sats/2.0"})
        data = urllib.request.urlopen(req, timeout=60).read()
        if len(data) > 100_000:  # sanity: full set is ~1.5 MB
            tmp = TLE_PATH + ".tmp"
            with open(tmp, "wb") as f:
                f.write(data)
            os.replace(tmp, TLE_PATH)
            os.chmod(TLE_PATH, 0o644)
    except Exception as e:
        if not os.path.isfile(TLE_PATH):
            raise RuntimeError(f"TLE download failed and no cache: {e}")
        # stale cache is acceptable


def load_tles():
    sats = []
    with open(TLE_PATH) as f:
        lines = [l.strip() for l in f if l.strip()]
    for i in range(0, len(lines) - 2, 3):
        name, l1, l2 = lines[i], lines[i + 1], lines[i + 2]
        if not (l1.startswith("1 ") and l2.startswith("2 ")):
            continue
        try:
            sats.append((name, Satrec.twoline2rv(l1, l2)))
        except Exception:
            continue
    return sats


def gmst_deg(jd_full):
    t = jd_full - 2451545.0
    g = 280.46061837 + 360.98564736629 * t
    return g % 360.0


def geodetic_to_ecef(lat_deg, lon_deg, alt_km):
    lat, lon = math.radians(lat_deg), math.radians(lon_deg)
    n = WGS84_A / math.sqrt(1 - WGS84_E2 * math.sin(lat) ** 2)
    x = (n + alt_km) * math.cos(lat) * math.cos(lon)
    y = (n + alt_km) * math.cos(lat) * math.sin(lon)
    z = (n * (1 - WGS84_E2) + alt_km) * math.sin(lat)
    return x, y, z


def teme_to_ecef(r, jd_full):
    th = math.radians(gmst_deg(jd_full))
    c, s = math.cos(th), math.sin(th)
    x = c * r[0] + s * r[1]
    y = -s * r[0] + c * r[1]
    return x, y, r[2]


def az_el(obs_ecef, sat_ecef, lat_deg, lon_deg):
    lat, lon = math.radians(lat_deg), math.radians(lon_deg)
    dx = sat_ecef[0] - obs_ecef[0]
    dy = sat_ecef[1] - obs_ecef[1]
    dz = sat_ecef[2] - obs_ecef[2]
    # ECEF -> ENU
    e = -math.sin(lon) * dx + math.cos(lon) * dy
    n = (-math.sin(lat) * math.cos(lon) * dx
         - math.sin(lat) * math.sin(lon) * dy
         + math.cos(lat) * dz)
    u = (math.cos(lat) * math.cos(lon) * dx
         + math.cos(lat) * math.sin(lon) * dy
         + math.sin(lat) * dz)
    rng = math.sqrt(e * e + n * n + u * u)
    el = math.degrees(math.asin(u / rng))
    az = math.degrees(math.atan2(e, n)) % 360.0
    return az, el, rng


def ang_sep(az1, el1, az2, el2):
    a1, e1 = math.radians(az1), math.radians(el1)
    a2, e2 = math.radians(az2), math.radians(el2)
    cosd = (math.sin(e1) * math.sin(e2)
            + math.cos(e1) * math.cos(e2) * math.cos(a1 - a2))
    return math.degrees(math.acos(max(-1.0, min(1.0, cosd))))


def ensure_db(conn):
    conn.execute("""
        CREATE TABLE IF NOT EXISTS sats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL,
            visible_count INTEGER,
            best_name TEXT,
            best_norad INTEGER,
            best_az REAL,
            best_el REAL,
            best_range_km REAL,
            best_sep_deg REAL,
            top3 TEXT,
            bs_az REAL,
            bs_el REAL,
            error TEXT
        )
    """)
    conn.execute("CREATE INDEX IF NOT EXISTS idx_sats_ts ON sats(ts)")
    conn.commit()


def main():
    os.makedirs(DATA_DIR, exist_ok=True)
    db = sqlite3.connect(DB_PATH)
    ensure_db(db)

    ts = int(time.time())
    vals = {k: None for k in (
        "visible_count", "best_name", "best_norad", "best_az", "best_el",
        "best_range_km", "best_sep_deg", "top3", "bs_az", "bs_el")}
    err = None

    try:
        lat, lon, alt_km = get_location()
        bs_az, bs_el = get_boresight()
        vals["bs_az"], vals["bs_el"] = round(bs_az, 1), round(bs_el, 1)

        refresh_tle()
        sats = load_tles()
        if not sats:
            raise RuntimeError("no TLEs parsed")

        t = time.gmtime(ts)
        jd, fr = jday(t.tm_year, t.tm_mon, t.tm_mday,
                      t.tm_hour, t.tm_min, t.tm_sec)
        jd_full = jd + fr
        obs = geodetic_to_ecef(lat, lon, alt_km)

        visible = []
        for name, rec in sats:
            e, r, _v = rec.sgp4(jd, fr)
            if e != 0:
                continue
            sat_ecef = teme_to_ecef(r, jd_full)
            az, el, rng = az_el(obs, sat_ecef, lat, lon)
            if el >= EL_MASK_DEG:
                sep = ang_sep(az, el, bs_az, bs_el)
                visible.append((sep, name, rec.satnum, az, el, rng))

        vals["visible_count"] = len(visible)
        if visible:
            visible.sort(key=lambda x: x[0])
            sep, name, norad, az, el, rng = visible[0]
            vals.update(best_name=name, best_norad=norad,
                        best_az=round(az, 1), best_el=round(el, 1),
                        best_range_km=round(rng, 1), best_sep_deg=round(sep, 1))
            vals["top3"] = json.dumps([
                {"name": v[1], "norad": v[2], "sep": round(v[0], 1),
                 "el": round(v[4], 1)}
                for v in visible[:TOP_N]])

        # Full sky snapshot for the UI map (overwritten every run).
        # The published observer position is rounded so the exact QTH is not
        # exposed by the unauthenticated sky.php endpoint.
        prec = (_cfg.get("location", {}) or {}).get("publish_precision", 2)
        prec = max(0, min(6, int(prec)))
        sky = {
            "ts": ts,
            "qth": {"lat": round(lat, prec), "lon": round(lon, prec),
                    "alt_km": round(alt_km, 3)},
            "bs_az": round(bs_az, 1), "bs_el": round(bs_el, 1),
            "el_mask": EL_MASK_DEG,
            "best_norad": visible[0][2] if visible else None,
            "sats": [
                {"name": v[1], "norad": v[2], "az": round(v[3], 1),
                 "el": round(v[4], 1), "sep": round(v[0], 1),
                 "rng": round(v[5], 0)}
                for v in sorted(visible, key=lambda x: -x[4])
            ],
        }
        tmp = os.path.join(DATA_DIR, "sky.json.tmp")
        dst = os.path.join(DATA_DIR, "sky.json")
        with open(tmp, "w") as f:
            json.dump(sky, f)
        os.replace(tmp, dst)
        os.chmod(dst, 0o644)
    except Exception as e:
        err = f"{type(e).__name__}: {e}"

    db.execute(
        """INSERT INTO sats
           (ts, visible_count, best_name, best_norad, best_az, best_el,
            best_range_km, best_sep_deg, top3, bs_az, bs_el, error)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)""",
        (ts, vals["visible_count"], vals["best_name"], vals["best_norad"],
         vals["best_az"], vals["best_el"], vals["best_range_km"],
         vals["best_sep_deg"], vals["top3"], vals["bs_az"], vals["bs_el"], err),
    )
    db.commit()
    db.close()

    if err:
        print(f"cfspeed-sats: FAILED — {err}", file=sys.stderr)
        sys.exit(1)
    print(f"cfspeed-sats: {vals['visible_count']} visible >= {EL_MASK_DEG}deg | "
          f"candidate {vals['best_name']} (NORAD {vals['best_norad']}) "
          f"sep {vals['best_sep_deg']}deg el {vals['best_el']}deg "
          f"rng {vals['best_range_km']} km")


if __name__ == "__main__":
    main()
