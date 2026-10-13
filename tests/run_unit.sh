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
php tests/global_role_permissions.php
php tests/plan_contract.php
php tests/login_transition_handoff.php
php tests/logout_handoff.php
php tests/request_disposal.php
php tests/disposal_view_details.php
php tests/role_lifecycle.php
php tests/audit_listing.php
php tests/retention_countdown.php
php tests/topnav_optional_asset.php
php tests/document_folder_layout.php
php tests/folder_search.php
php tests/hardcopy_folder_projection.php
php tests/hardcopy_contextual_folders.php
php tests/colors_actions_contract.php
php tests/plan_implementation.php
php tests/backup_contract.php
php tests/backup_retention.php
php tests/high_priority_security.php
php tests/dashboard_copy_contract.php
node --test tests/document_view.test.js tests/dashboard_visual.test.js tests/motion_navigation.test.js tests/action_feedback.test.js tests/login_transition.test.js tests/logout_transition.test.js
echo "Static and syntax checks passed."
