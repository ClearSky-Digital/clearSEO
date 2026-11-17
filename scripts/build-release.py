"""Build csd-seo.zip from the repo, leaving out everything listed in .distignore.

Run from anywhere: python scripts/build-release.py [output-folder]
The zip holds one csd-seo/ folder. Paths are relative to this file.
"""
import fnmatch
import os
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = sys.argv[1] if len(sys.argv) > 1 else ROOT
skip = [l.strip() for l in open(os.path.join(ROOT, ".distignore"), encoding="utf-8") if l.strip()]


def skipped(rel):
    parts = rel.split("/")
    return any(fnmatch.fnmatch(p, pat) for p in parts for pat in skip)


target = os.path.join(OUT, "csd-seo.zip")
with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk(ROOT):
        dirs[:] = sorted(d for d in dirs if not skipped(os.path.relpath(os.path.join(base, d), ROOT).replace(os.sep, "/")))
        for f in sorted(files):
            rel = os.path.relpath(os.path.join(base, f), ROOT).replace(os.sep, "/")
            if not skipped(rel) and os.path.abspath(os.path.join(base, f)) != os.path.abspath(target):
                z.write(os.path.join(base, f), "csd-seo/" + rel)
print(target)
