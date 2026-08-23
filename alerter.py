#!/usr/bin/env python3
"""cfspeed alerter v2.0 — reads new DB rows, sends Telegram alerts per config.

State in data/alert_state.json prevents duplicate alerts. Messages that
cannot be delivered (Telegram down, rate cap) are queued in the state file
and retried on the next run instead of being dropped.

Also handles optional DB retention: "retention": {"days": N} in config.json
prunes rows older than N days once per day (0 = keep forever).

Run by systemd timer every minute.
"""
import json
import os
import sqlite3
import sys
import time
import urllib.request

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
DB_PATH = os.path.join(DATA_DIR, "speed.db")
CONFIG = os.path.join(DATA_DIR, "config.json")
STATE = os.path.join(DATA_DIR, "alert_state.json")

MAX_SEND_PER_RUN = 10   # Telegram flood protection
MAX_QUEUE = 50          # cap on undelivered backlog


def load_json(path, default):
    try:
        with open(path) as f:
            return json.load(f)
    except Exception:
        return default


def save_state(st):
    tmp = STATE + ".tmp"
    with open(tmp, "w") as f:
        json.dump(st, f)
    os.replace(tmp, STATE)


def tg_send(cfg, text):
    tok = cfg.get("telegram", {}).get("token", "")
    chat = cfg.get("telegram", {}).get("chat_id", "")
    if not tok or not chat:
        return False
    try:
        req = urllib.request.Request(
            f"https://api.telegram.org/bot{tok}/sendMessage",
            data=json.dumps({"chat_id": chat, "text": text}).encode(),
            headers={"Content-Type": "application/json"},
        )
        urllib.request.urlopen(req, timeout=10).read()
        return True
    except Exception as e:
        print(f"alerter: telegram send failed: {e}", file=sys.stderr)
        return False


def collect_events(db, cfg, st, now):
    """Scan new DB rows, return alert messages and update scan state."""
    al = cfg.get("alerts", {})
    msgs = []

    # ---------- speed test results ----------
    last_rid = st.get("last_result_id", None)
    if last_rid is None:
        row = db.execute("SELECT MAX(id) m FROM results").fetchone()
        last_rid = row["m"] or 0  # first run: skip backlog
    rows = db.execute(
        "SELECT * FROM results WHERE id > ? ORDER BY id", (last_rid,)).fetchall()
    for r in rows:
        last_rid = r["id"]
        t = time.strftime("%H:%M", time.localtime(r["ts"]))
        if r["error"] is not None:
            if al.get("test_fail"):
                msgs.append(f"❌ Speed test FAILED at {t}\n{r['error']}")
            continue
        if al.get("retry") and (r["retries"] or 0) > 0:
            msgs.append(f"⚠️ Speed test needed {r['retries']} "
                        f"retr{'y' if r['retries']==1 else 'ies'} at {t}\n"
                        f"Final: {r['down_mbps']}↓ / {r['up_mbps']}↑ Mbps")
        if al.get("low_speed"):
            thr = float(al.get("low_speed_mbps", 20))
            if (r["down_mbps"] or 0) < thr:
                msgs.append(f"\U0001f40c Low download at {t}: "
                            f"{r['down_mbps']} Mbps (threshold {thr})")
        ip = r["client_ip"]
        if ip and st.get("last_ip") and ip != st["last_ip"] and al.get("new_ip"):
            msgs.append(f"\U0001f310 WAN IP changed\n{st['last_ip']} → {ip}")
        if ip:
            st["last_ip"] = ip
    st["last_result_id"] = last_rid

    # ---------- dish telemetry ----------
    has_dish = db.execute(
        "SELECT COUNT(*) c FROM sqlite_master WHERE type='table' AND name='dish'"
    ).fetchone()["c"]
    if has_dish:
        last_did = st.get("last_dish_id", None)
        if last_did is None:
            row = db.execute("SELECT MAX(id) m FROM dish").fetchone()
            last_did = row["m"] or 0
        drows = db.execute(
            "SELECT * FROM dish WHERE id > ? ORDER BY id", (last_did,)).fetchall()
        for r in drows:
            last_did = r["id"]
            t = time.strftime("%H:%M", time.localtime(r["ts"]))
            if r["error"] is not None:
                if not st.get("dish_down") and al.get("dish_down"):
                    msgs.append(f"\U0001f4e1 Dish UNREACHABLE at {t}\n{r['error']}")
                st["dish_down"] = True
                continue
            if st.get("dish_down"):
                st["dish_down"] = False
                if al.get("dish_down"):
                    msgs.append(f"✅ Dish back online at {t}")
            if al.get("dish_hw") and r["alerts"]:
                try:
                    active = json.loads(r["alerts"])
                except Exception:
                    active = []
                key = ",".join(sorted(active))
                if active and key != st.get("hw_alerts_key") \
                        and now - st.get("hw_alerts_ts", 0) > 1800:
                    st["hw_alerts_key"] = key
                    st["hw_alerts_ts"] = now
                    msgs.append(f"\U0001f6a8 Dish hardware alerts at {t}:\n"
                                + "\n".join(f"• {a}" for a in active))
                if not active:
                    st["hw_alerts_key"] = ""
            if al.get("high_drop") and r["drop_rate_60s"] is not None:
                pct = r["drop_rate_60s"] * 100
                thr = float(al.get("drop_pct", 5))
                if pct >= thr and now - st.get("drop_ts", 0) > 900:
                    st["drop_ts"] = now
                    msgs.append(f"\U0001f4c9 High drop rate at {t}: "
                                f"{pct:.1f}% over last 60s (threshold {thr}%)")
        st["last_dish_id"] = last_did

    return msgs


def prune_old_rows(db, cfg, st, now):
    """Optional retention: delete rows older than retention.days, daily."""
    try:
        days = int(cfg.get("retention", {}).get("days") or 0)
    except Exception:
        days = 0
    if days <= 0 or now - st.get("prune_ts", 0) < 86400:
        return
    cutoff = now - days * 86400
    for tbl in ("results", "dish", "sats"):
        try:
            db.execute(f"DELETE FROM {tbl} WHERE ts < ?", (cutoff,))
        except sqlite3.OperationalError:
            pass  # table may not exist yet
    db.commit()
    st["prune_ts"] = now


def main():
    cfg = load_json(CONFIG, {})
    st = load_json(STATE, {})
    db = sqlite3.connect(DB_PATH)
    db.row_factory = sqlite3.Row
    now = int(time.time())

    msgs = collect_events(db, cfg, st, now)
    prune_old_rows(db, cfg, st, now)
    db.close()

    tg = cfg.get("telegram", {})
    tg_ready = bool(tg.get("token") and tg.get("chat_id"))

    # Queue new messages behind any undelivered backlog, then send in order.
    pending = (st.get("pending") or []) + msgs
    pending = pending[-MAX_QUEUE:]
    sent = 0
    if tg_ready:
        while pending and sent < MAX_SEND_PER_RUN:
            if not tg_send(cfg, pending[0]):
                break               # Telegram down — retry the rest next run
            pending.pop(0)
            sent += 1
        st["pending"] = pending
    else:
        st["pending"] = []  # nowhere to send — don't hoard a backlog

    save_state(st)
    print(f"alerter: {len(msgs)} new events, {sent} sent, "
          f"{len(st['pending'])} queued")


if __name__ == "__main__":
    main()
