#!/usr/bin/env python3
"""cfspeed updater v1.0 — pull a new release from GitHub and reinstall.

Triggered as root by cfspeed-update.path when the dashboard writes
data/update_request.json. Never run directly from the web server: the web
user only writes the request file, this runs with the privileges needed to
replace files and restart timers.

Flow:
  1. read the requested ref from data/update_request.json
  2. fetch the repo into data/.update-src (clone on first run)
  3. check out the requested tag
  4. back up the current install, then run install.sh from the checkout
  5. write progress/result to data/update_status.json throughout

Rollback: the pre-update tree is kept in data/.update-backup so a failed
install can be restored by hand (the path is printed in the status file).
"""
import json
import os
import shutil
import subprocess
import sys
import time

BASE = os.environ.get("CFSPEED_HOME") or os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
REQUEST = os.path.join(DATA_DIR, "update_request.json")
STATUS = os.path.join(DATA_DIR, "update_status.json")
SRC_DIR = os.path.join(DATA_DIR, ".update-src")
BACKUP_DIR = os.path.join(DATA_DIR, ".update-backup")
VERSION_FILE = os.path.join(BASE, "VERSION")

DEFAULT_REPO = "https://github.com/razvanzeces/stardashy.git"
# Only refs that look like a version tag (or "main") are ever checked out.
SAFE_REF = lambda r: bool(r) and (
    r == "main" or all(c.isalnum() or c in ".-_" for c in r)) and len(r) <= 64

INSTALLED_FILES = ["collector.py", "dish_collector.py", "sat_tracker.py",
                   "alerter.py", "apply_config.py", "update.py", "VERSION"]


def set_status(state, message, **extra):
    st = {"state": state, "message": message, "ts": int(time.time())}
    st.update(extra)
    tmp = STATUS + ".tmp"
    with open(tmp, "w") as f:
        json.dump(st, f)
    os.replace(tmp, STATUS)
    os.chmod(STATUS, 0o664)
    print(f"cfspeed-update: [{state}] {message}", flush=True)
    return st


def run(argv, cwd=None, timeout=600):
    p = subprocess.run(argv, cwd=cwd, capture_output=True, text=True,
                       timeout=timeout)
    if p.returncode != 0:
        raise RuntimeError(
            f"{' '.join(argv[:3])} failed: {(p.stderr or p.stdout).strip()[:300]}")
    return p.stdout


def local_version():
    try:
        with open(VERSION_FILE) as f:
            return f.read().strip()
    except Exception:
        return "0.0.0"


def fetch_source(repo, ref):
    """Clone or update the cached checkout, then check out ref."""
    if not shutil.which("git"):
        raise RuntimeError("git is not installed on this system")

    if not os.path.isdir(os.path.join(SRC_DIR, ".git")):
        shutil.rmtree(SRC_DIR, ignore_errors=True)
        set_status("running", "Cloning repository…")
        run(["git", "clone", "--quiet", repo, SRC_DIR])
    else:
        set_status("running", "Fetching updates…")
        run(["git", "remote", "set-url", "origin", repo], cwd=SRC_DIR)
        run(["git", "fetch", "--quiet", "--tags", "--force", "origin"], cwd=SRC_DIR)

    set_status("running", f"Checking out {ref}…")
    run(["git", "checkout", "--quiet", "--force", ref], cwd=SRC_DIR)
    if ref == "main":
        run(["git", "reset", "--quiet", "--hard", "origin/main"], cwd=SRC_DIR)

    new_ver = "unknown"
    try:
        with open(os.path.join(SRC_DIR, "VERSION")) as f:
            new_ver = f.read().strip()
    except Exception:
        pass
    return new_ver


def backup_current():
    shutil.rmtree(BACKUP_DIR, ignore_errors=True)
    os.makedirs(BACKUP_DIR, exist_ok=True)
    for name in INSTALLED_FILES:
        src = os.path.join(BASE, name)
        if os.path.isfile(src):
            shutil.copy2(src, os.path.join(BACKUP_DIR, name))
    www = os.path.join(BASE, "www")
    if os.path.isdir(www):
        shutil.copytree(www, os.path.join(BACKUP_DIR, "www"),
                        dirs_exist_ok=True,
                        ignore=shutil.ignore_patterns("assets"))


def main():
    os.makedirs(DATA_DIR, exist_ok=True)

    req = {}
    try:
        with open(REQUEST) as f:
            req = json.load(f)
    except Exception:
        pass
    # The request file is consumed immediately so a stale trigger can never
    # re-run an update on the next path-unit activation.
    try:
        os.remove(REQUEST)
    except OSError:
        pass

    ref = str(req.get("ref") or "").strip()
    repo = str(req.get("repo") or DEFAULT_REPO).strip()
    if not SAFE_REF(ref):
        set_status("error", "No valid version requested")
        return 1
    if not repo.startswith("https://github.com/") or not repo.endswith(".git"):
        set_status("error", "Refusing to update from an unexpected repository")
        return 1

    from_ver = local_version()
    set_status("running", "Starting update…", from_version=from_ver, to_ref=ref)

    try:
        new_ver = fetch_source(repo, ref)

        set_status("running", "Backing up current install…",
                   from_version=from_ver, to_ref=ref)
        backup_current()

        installer = os.path.join(SRC_DIR, "install.sh")
        if not os.path.isfile(installer):
            raise RuntimeError("install.sh missing from the downloaded release")
        os.chmod(installer, 0o755)

        set_status("running", f"Installing {new_ver}…",
                   from_version=from_ver, to_ref=ref)
        env = dict(os.environ, CFSPEED_DEST=BASE)
        p = subprocess.run(["bash", installer], cwd=SRC_DIR, env=env,
                           capture_output=True, text=True, timeout=900)
        if p.returncode != 0:
            raise RuntimeError(
                "installer failed: " + (p.stderr or p.stdout).strip()[-400:])

        set_status("done", f"Updated to {new_ver}",
                   from_version=from_ver, to_version=new_ver,
                   backup=BACKUP_DIR)
        return 0
    except Exception as e:
        set_status("error", f"{type(e).__name__}: {e}"[:400],
                   from_version=from_ver, to_ref=ref, backup=BACKUP_DIR)
        return 1


if __name__ == "__main__":
    sys.exit(main())
