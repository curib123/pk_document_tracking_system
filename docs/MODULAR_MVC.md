# Native CodeIgniter 3 modular MVC

This branch starts an incremental move to traditional server-rendered CodeIgniter 3 MVC. It is **not** a completed API removal.

## Layout and routing

A feature lives under `application/modules/<feature>/controllers`, `models` and `views` as needed. CodeIgniter 3 does not automatically discover modules. Instead, keep a thin controller in `application/controllers` for CI3's normal router, which loads the actual module controller using `require_once`. Existing route URLs therefore remain stable, with no additional HMVC vendor framework or router rewrite.

Dashboard pilot:
- `application/controllers/Web_dashboard.php`: CI3 compatibility bridge.
- `application/modules/dashboard/controllers/Dashboard_controller.php`: native server-side controller.
- `application/modules/dashboard/views/index.php`: feature-owned PHP view.
- `MY_Web_Controller::webView`: accepts trusted, hardcoded `modules/...` paths as well as pre-existing `web/...` views.

Do not accept a module view path from user input. Keep shared header/footer, session checks, CSRF, authorization, and service/database behavior unchanged.

## Gradual migration

Move catalogues, requests, workflow versioning, documents, transfers, disposal, files and administration module by module. Create ordinary HTML POST forms with CSRF and POST/redirect/GET, preserving existing transactional services and Query Builder models. Do not remove the JSON endpoints or JS/CSS legacy workspace until each replacement is functionally complete and regression tested.

## Verification

Run `php tests/modular_mvc.php`, `php tests/web_mvc.php`, `php tests/run.php`, and the browser/integration suites with PHP 8.0 and XAMPP/MySQL. Static assertions do not replace real browser and database testing.

## Status

Only the native dashboard has been moved to the feature module so far. All other legacy and native browser features remain available through their original routes.
