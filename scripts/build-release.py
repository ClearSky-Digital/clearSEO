"""Build clearseo-<version>.zip from the files git tracks, minus everything listed in .distignore.

Run from anywhere: python scripts/build-release.py [output-folder]
Only tracked files can go in, so stray files (.env, logs, backups, old zips) never reach the package.
The build refuses when a tracked file has uncommitted changes, so the zip always matches a commit.
The zip holds one clearseo/ folder. Paths are relative to this file.
"""
import fnmatch
import os
import re
import subprocess
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = sys.argv[1] if len(sys.argv) > 1 else ROOT


def git(*args):
    r = subprocess.run(["git", "-C", ROOT, *args], capture_output=True, text=True)
    if r.returncode:
        sys.exit("git failed: " + r.stderr.strip())
    return r.stdout


if git("status", "--porcelain", "--untracked-files=no").strip():
    sys.exit("Refusing to build: tracked files have uncommitted changes. Commit them first.")

skip = [l.strip() for l in open(os.path.join(ROOT, ".distignore"), encoding="utf-8") if l.strip()]


def skipped(rel):
    return any(fnmatch.fnmatch(p, pat) for p in rel.split("/") for pat in skip)


VERSION = re.search(r"^\s*\*\s*Version:\s*(\S+)", open(os.path.join(ROOT, "clearseo.php"), encoding="utf-8").read(), re.M).group(1)
target = os.path.join(OUT, "clearseo-%s.zip" % VERSION)
with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as z:
    for rel in sorted(git("ls-files").splitlines()):
        if not skipped(rel):
            z.write(os.path.join(ROOT, rel), "clearseo/" + rel)
print(target)
