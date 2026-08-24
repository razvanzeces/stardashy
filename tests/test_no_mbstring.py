#!/usr/bin/env python3
"""Fail if any PHP file calls mbstring without guarding it.

mbstring ships as a separate package on Debian and Raspberry Pi OS and is not
installed by default. An unguarded call is a fatal error at runtime, which
turns a JSON endpoint into an HTML error page — the dashboard then shows a
blank panel or "bad response" with nothing in the logs pointing at the cause.
This has now bitten twice (v3.1.2 in update.php, v3.5.0 in energy.php), hence
the test.

Calls wrapped in function_exists('mb_...') are fine.

Run: python3 tests/test_no_mbstring.py
"""
import glob
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CALL = re.compile(r"\bmb_[a-z_]+\s*\(")
GUARD = re.compile(r"function_exists\(\s*['\"]mb_")

bad = []
for path in sorted(glob.glob(os.path.join(ROOT, "www", "*.php"))):
    lines = open(path, encoding="utf-8").read().splitlines()
    for i, line in enumerate(lines):
        if not CALL.search(line):
            continue
        # a guard on the same line or the two above it counts as protection
        window = "\n".join(lines[max(0, i - 2):i + 1])
        if GUARD.search(window):
            continue
        bad.append(f"{os.path.relpath(path, ROOT)}:{i + 1}: {line.strip()}")

if bad:
    print("unguarded mbstring calls:")
    for b in bad:
        print("  " + b)
    sys.exit(1)
print("  no unguarded mbstring calls in www/*.php")
