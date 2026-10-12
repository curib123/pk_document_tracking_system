#!/usr/bin/env bash
set -euo pipefail
echo "Linting tracked PHP source..."
while IFS= read -r file; do
  php -l "$file" >/dev/null
done < <(git ls-files '*.php')
echo "Checking JavaScript syntax..."
while IFS= read -r file; do
  node --check "$file"
done < <(git ls-files '*.js')
echo "Running workflow and plan contract tests..."
php tests/plan_contract.php
node --test tests/document_view.test.js
echo "Static and syntax checks passed."
