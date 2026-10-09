#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php tests/lint.php
for test in static_contract ux_ui_contract login_layout source_schema_contract required_services route_views auth_policy account_policy \
    document_scope query_state folder_tree category_parent request_policy workflow_graph workflow_view dashboard_view; do
    php "tests/$test.php"
done
for file in public/assets/js/*.js; do node --check "$file"; done
python3 -m py_compile tests/plan_integration.py tests/browser_regression.py
echo 'PHP unit/view/schema checks, all application JS syntax and Python test syntax passed.'
