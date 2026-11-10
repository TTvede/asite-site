#!/usr/bin/env bash
# Byg release-zip: dist/asite-site.zip med mappen asite-site/ i roden.
set -euo pipefail
cd "$(dirname "$0")"
rm -rf dist && mkdir -p dist
for f in asite-site/*.php asite-site/includes/*.php; do php -l "$f" >/dev/null; done
python3 - <<'P'
import zipfile, os
with zipfile.ZipFile('dist/asite-site.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for root, _, files in os.walk('asite-site'):
        for f in files:
            z.write(os.path.join(root, f))
print('dist/asite-site.zip')
P
