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

## Controller permission and session architecture (October 2026)

All CI3 controller classes inherit from application/core/MY_Controller.php.
It loads CodeIgniter's Session library centrally, using the files driver and
private storage/sessions directory. This includes the native MVC screens and
the temporarily retained legacy API routes.

The MY_Controller base owns:

- CI session creation and CSRF token generation.
- User restoration and session-version validation on every authenticated page.
- The 30-minute idle timeout and forced initial-password-change redirect.
- Auth login/password/logout session transitions and session ID regeneration.
- One reusable require_permission(permission, context) action gate.

Every protected native controller function must call require_permission()
before reading or changing data. The view permission on index/detail, add/edit
on forms, and delete on deletion screens must be checked explicitly.
Domain services continue to validate permissions and business constraints.

Auth_service handles password verification, rate limiting, password updates
and audit. It does not read or mutate PHP sessions. After successfully
committing a service operation, Auth_controller and the transitional
Http_gateway call MY_Controller::_complete_auth_session() to update the CI
session. The leading underscore prevents public URL routing to that method.

Native catalog drafts use CI Session userdata; error/notice messages use CI
flashdata. Browser screens do not access raw PHP $_SESSION.

The CI session cookie is named pk_dts_ci_session. Previous homemade session
cookies are not migrated; users must sign in again after deployment. The
legacy gateway's standalone CLI fallback is retained until the legacy API
migration is completed.

Verification: php tests/ci_session.php plus the disposable-MySQL
tests/web_ci_session.py (after tests/native_routes.py). Existing database
and real-browser tests remain mandatory before merging.
