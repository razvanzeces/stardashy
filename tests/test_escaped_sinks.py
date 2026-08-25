#!/usr/bin/env python3
"""Fail if the esc() XSS helper is missing from www/index.php or any protected innerHTML sink loses its escaping."""
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PATH = os.path.join(ROOT, "www", "index.php")

FORBIDDEN = [
    "${t.error}",
    "${i.hostname ||",
    "+ h.org :",
    "+ h.cc :",
    "${h.ptr}",
    "${s.name}",
    "${hit.s.name}",
    "${r.values.join",
    "${d.name",
]

src = open(PATH, encoding="utf-8").read()
bad = []
if "const esc =" not in src:
    bad.append("www/index.php: missing `const esc =` helper")
for i, line in enumerate(src.splitlines()):
    for needle in FORBIDDEN:
        if needle in line:
            bad.append(f"www/index.php:{i + 1}: raw `{needle}`: {line.strip()}")

if bad:
    print("unescaped sink regressions:")
    for b in bad:
        print("  " + b)
    sys.exit(1)
print("  esc helper present and all protected sinks escaped in www/index.php")
