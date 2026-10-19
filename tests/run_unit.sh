#!/usr/bin/env bash
set -Euo pipefail
# Run every independent regression even when one fails, then fail the suite.
failed=0
trap 'failed=$((failed+1))' ERR
echo "Linting tracked PHP source..."
while IFS= read -r file; do
  php -l "$file" >/dev/null
done < <(git ls-files '*.php')
echo "Checking JavaScript syntax..."
while IFS= read -r file; do
  node --check "$file"
done < <(git ls-files '*.js')
echo "Running workflow and plan contract tests..."
php tests/user_role_permissions.php
php tests/hardcopy_after_transfer_scope.php
php tests/approval_step_tiles.php
php tests/request_view_new_types.php
php tests/controlled_file_history.php
php tests/controlled_file_print.php
php tests/file_action_permissions.php
php tests/file_response_safety.php
php tests/action_errors.php
php tests/backup_listing.php
php tests/backup_listing_regression.php
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
php tests/current_plan_contract.php
php tests/date_range.php
php tests/category_assignment.php
php tests/root_urls.php
php tests/pwa_manifest.php
php tests/backup_contract.php
php tests/backup_retention.php
php tests/high_priority_security.php
php tests/dashboard_copy_contract.php
node --test tests/document_view.test.js tests/controlled_print.test.js tests/pwa.test.js tests/date_controls.test.js tests/dashboard_visual.test.js tests/motion_navigation.test.js tests/action_feedback.test.js tests/login_transition.test.js tests/logout_transition.test.js tests/row_actions.test.js tests/task_notifications.test.js tests/modal_dismissal.test.js tests/live_table_contract.test.js
if (( failed > 0 )); then
  echo "$failed check(s) failed; see the errors above." >&2
  exit 1
fi
echo "Static and syntax checks passed."
