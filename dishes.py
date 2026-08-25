#!/usr/bin/env python3
"""cfspeed multi-dish resolution — shared by the collectors.

Every Starlink dish answers on 192.168.100.1:9200. That address is fixed in
firmware, so two dishes reachable from one host are two different machines at
the same address, and no amount of cleverness inside this program can tell
them apart. The network has to be made unambiguous outside it — DNAT to
distinct addresses, a VLAN or subnet per dish, a network namespace per NIC,
or a collector running next to each dish.

So a dish here is not an address but a description of how to reach it:

    "dishes": [
      {"id": "home",  "name": "Home",  "target": "192.168.100.1:9200"},
      {"id": "barn",  "name": "Barn",  "target": "192.168.101.1:9200"},
      {"id": "cabin", "name": "Cabin", "target": "192.168.100.1:9200",
       "exec": "ssh pi@cabin", "location": {"lat": 46.5, "lon": 24.5}}
    ]

`exec` is a command prefix the gRPC call is run through, which covers
namespaces (`ip netns exec dish-b`), remote hosts (`ssh pi@cabin`) and
containers with one mechanism.

SECURITY: `exec` is arbitrary command execution and the collectors run as
root. It is therefore file-only — debug.php refuses to write it, so a
compromised dashboard password cannot turn into a root shell. Anyone editing
it already needs root on the box.
"""
import json
import os
import re
import shlex

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
CONFIG = os.path.join(BASE, "data", "config.json")

DEFAULT_TARGET = "192.168.100.1:9200"
DEFAULT_ID = "default"
MAX_DISHES = 8

# Ids end up in file paths and SQL parameters, so keep them boring.
_ID_RE = re.compile(r"^[a-z0-9][a-z0-9_-]{0,31}$")
# host:port, nothing that could be read as a flag by grpcurl
_TARGET_RE = re.compile(r"^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?(:\d{1,5})?$")


def load_config(path=None):
    try:
        with open(path or CONFIG) as f:
            return json.load(f)
    except Exception:
        return {}


def _clean_id(raw, fallback):
    raw = str(raw or "").strip().lower().replace(" ", "-")
    return raw if _ID_RE.match(raw) else fallback


def resolve(cfg=None):
    """Return the configured dishes as a list of dicts.

    A config without a "dishes" list yields a single dish built from the old
    single-dish "dish" block, so existing installs keep working untouched and
    their rows keep the same id.
    """
    cfg = cfg if cfg is not None else load_config()
    raw = cfg.get("dishes")
    legacy = cfg.get("dish") or {}

    if not isinstance(raw, list) or not raw:
        return [{
            "id": DEFAULT_ID,
            "name": str(legacy.get("name") or "Dish"),
            "target": str(legacy.get("target") or DEFAULT_TARGET),
            "exec": _split_exec(legacy.get("exec")),
            "grpcurl": legacy.get("grpcurl") or None,
            "location": cfg.get("location") or {},
        }]

    out, seen = [], set()
    for i, d in enumerate(raw[:MAX_DISHES]):
        if not isinstance(d, dict):
            continue
        did = _clean_id(d.get("id"), f"dish{i + 1}")
        while did in seen:                      # ids must stay unique
            did = f"{did}-{i + 1}"
        seen.add(did)

        target = str(d.get("target") or DEFAULT_TARGET).strip()
        if not _TARGET_RE.match(target):
            continue                            # skip rather than shell out to it
        out.append({
            "id": did,
            "name": str(d.get("name") or did.title())[:40],
            "target": target,
            "exec": _split_exec(d.get("exec")),
            "grpcurl": d.get("grpcurl") or None,
            # Per-dish location falls back to the global one, which is what a
            # single-site install with two dishes wants.
            "location": d.get("location") or cfg.get("location") or {},
        })
    return out or resolve({"dish": legacy, "location": cfg.get("location")})


def _split_exec(raw):
    """Parse the exec prefix into argv. Never passed to a shell."""
    if not raw:
        return []
    try:
        return shlex.split(str(raw))
    except ValueError:
        return []


def by_id(dish_id, cfg=None):
    for d in resolve(cfg):
        if d["id"] == dish_id:
            return d
    return None
