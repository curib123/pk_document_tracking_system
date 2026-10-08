# Native CodeIgniter 3 modular MVC

This branch starts an incremental move to traditional server-rendered CodeIgniter 3 MVC. It is **not** a completed API removal.

## Layout and routing

A feature lives under `application/modules/<feature>/controllers`, `models` and `views` as needed. CodeIgniter 3 does not automatically discover modules. Instead, keep a thin controller in `application/controllers` for CI3's normal router, which loads the actual module controller using `require_once`. Existing route URLs therefore remain stable, with no additional HMVC vendor framework or router rewrite.

Initial modules:
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

The native dashboard, authentication, catalogue and read-only records controllers now live in feature modules behind CodeIgniter 3 route bridges. Their existing native PHP views remain under `application/views/web` until a later view relocation. The default entry route now opens the Bootstrap-only dashboard, and `/login` uses the native login. The old `/app` workspace and API routes remain available because several transactional workflows have not yet been converted. These legacy routes still depend on custom JavaScript/CSS; full removal would break those features.


## Enterprise shared UI components

The native module controllers now use shared PHP view components, custom
enterprise CSS and a local jQuery enhancement script. See
docs/ENTERPRISE_MVC_UI.md for central ownership, server-side filtering,
searchable dropdowns, alert/confirmation/form modals, accessibility,
asset fallback and migration limitations. The controller bridges and
business-domain services retain the normal CI3 MVC separation.
