#!/usr/bin/env bash
# Existing application dependencies, pinned for deterministic offline rendering tests.
set -euo pipefail
DEST="${PK_BROWSER_ASSETS:?Set PK_BROWSER_ASSETS to a temporary test-assets directory}"
mkdir -p "$DEST"
curl --fail --silent --show-error --retry 3 -L https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css -o "$DEST/bootstrap.min.css"
curl --fail --silent --show-error --retry 3 -L https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js -o "$DEST/bootstrap.bundle.min.js"
curl --fail --silent --show-error --retry 3 -L https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js -o "$DEST/chart.umd.min.js"
curl --fail --silent --show-error --retry 3 -L https://code.jquery.com/jquery-3.7.1.min.js -o "$DEST/jquery-3.7.1.min.js"
(cd "$DEST" && sha256sum *.css *.js > checksums.txt)
