#!/usr/bin/env python3
"""Fail if a collector unit loses its systemd sandboxing or a root-only unit gains a User= line."""
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UNITDIR = os.path.join(ROOT, "systemd")

HARDENED = [
    "cfspeed.service",
    "cfspeed-dish.service",
    "cfspeed-sats.service",
    "cfspeed-icmp.service",
    "cfspeed-alerts.service",
]
PING_UNITS = {"cfspeed.service", "cfspeed-icmp.service"}
ROOT_UNITS = ["cfspeed-update.service", "cfspeed-apply.service"]

REQUIRED = [
    "User=cfspeed",
    "NoNewPrivileges=true",
    "ProtectSystem=strict",
    "ProtectHome=true",
    "PrivateTmp=true",
    "ReadWritePaths=/opt/cfspeed/data",
    "RestrictSUIDSGID=true",
    "SystemCallFilter=@system-service",
]


def unit_lines(name):
    return open(os.path.join(UNITDIR, name), encoding="utf-8").read().splitlines()


bad = []
for name in HARDENED:
    lines = unit_lines(name)
    for directive in REQUIRED:
        if directive not in lines:
            bad.append(f"{name}: missing {directive}")
    families = [l for l in lines if l.startswith("RestrictAddressFamilies=")]
    if not families or "AF_INET" not in families[0].split("=", 1)[1].split():
        bad.append(f"{name}: RestrictAddressFamilies must include AF_INET")
    caps = [l for l in lines if l.startswith("CapabilityBoundingSet=")]
    ambient = [l for l in lines if l.startswith("AmbientCapabilities=")]
    if not caps:
        bad.append(f"{name}: missing CapabilityBoundingSet")
    elif name in PING_UNITS:
        if caps != ["CapabilityBoundingSet=CAP_NET_RAW"]:
            bad.append(f"{name}: CapabilityBoundingSet must be exactly CAP_NET_RAW")
        if ambient != ["AmbientCapabilities=CAP_NET_RAW"]:
            bad.append(f"{name}: AmbientCapabilities must be exactly CAP_NET_RAW")
    else:
        if caps != ["CapabilityBoundingSet="]:
            bad.append(f"{name}: CapabilityBoundingSet must be exactly empty")
        if ambient:
            bad.append(f"{name}: must not carry AmbientCapabilities")

for name in ROOT_UNITS:
    for line in unit_lines(name):
        if line.startswith("User="):
            bad.append(f"{name}: must not set {line.strip()} — this unit needs root")

if bad:
    print("unit hardening regressions:")
    for b in bad:
        print("  " + b)
    sys.exit(1)
print("  collector units sandboxed as cfspeed; update/apply units stay root")
