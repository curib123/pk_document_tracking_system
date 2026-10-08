# Screenshot-based workspace and request-only catalog

## Visual implementation

The supplied dashboard and login screenshots define the visual target: dark burgundy sidebar, red active navigation, pale dashboard surface, rounded cards, welcome banner, document-library donut, recent records and a full-page login over the supplied building photograph. The bundled PK and Peanut Kisses marks are cropped from the supplied references; replace the small WebP files with original logo assets when higher-resolution originals are available.

`application/views/pages/account/login.php` and `application/views/pages/dashboard/index.php` own the reference HTML, with reusable components under `application/views/components/`. `workspace.js` only binds data and events to those views. See `docs/VIEW_ARCHITECTURE.md` for the page/component map. Native forms, request actions, CSRF, role permissions and workflow decisions remain owned by the existing application. The dashboard uses permission-filtered live data. Its labels are **Active documents** and **My requests in workflow**, rather than copying misleading sample counts/statuses from the reference. Latest-document rows use actual authorized records and open the existing document search.

The existing username/password sign-in form is retained. The password eye button is local-only; **Remember username on this device** remembers only the username, not the password or a persistent authentication token. There is no fake public registration link: account requests are directed to the Document Control Officer.

## Styling switches

`application/config/styling.php` remains authoritative. `account` and `dashboard` are enabled for the two supplied screens. Other existing module flags are preserved. Set any module to true to use the same shell and shared red components; false retains native HTML and its functionality. Turning the global switch off disables styling. Dark/light mode only affects enabled presentation and is stored on the current device.

Edit the PHP views for layout markup and `resources/workspace/reference.css` for presentation styles. Rebuild and commit the output together:

```sh
npm install
node tools/build-workspace.cjs
node tools/build-workspace.cjs --check
```

`public/assets/css/workspace.css` and the WebP images are committed. XAMPP does not require npm, a Node server, external fonts or an image CDN to run the application. Keep business rules out of presentation files.

## Access and Assignment request selectors

A separate capability, **documents.request_catalog**, permits discovery of active document titles/numbers in Access and Assignment request dropdowns. It does not grant ordinary document listing, detail, revision, attachment or download access. The server also checks the corresponding request capability, requests.add and the document module View permission. Other request types and direct actions do not use this catalog.

Assignment requests may request a document that the requester cannot yet read. Saving/submitting the request grants nothing: only final approval creates the assignment. Access requests retain their existing approval lifecycle. Inactive targets remain unavailable, including a supplied selected ID.

Roles without the catalog capability fall back to their ordinary assigned/granted dropdown scope. Configure the capability under Roles -> Assign permissions -> Documents. It is seeded for Staff, Administrator, Document Control Officer and Plant Manager; this never seeds documents.view_all on Staff.

After pulling this branch into an existing schema-v7 installation, back up the database and run from the repository root:

```bat
C:\xampp\php\php.exe bin\sync_request_catalog.php
```

The command creates the capability and initial role bindings once, transactionally. Repeated runs preserve role customizations. Structural schema version remains 7. Fresh installations already include the permission.

## Verification

- Request-catalog regression was observed failing before implementation, then passing with the separate catalog.
- tests/request_catalog.php covers minimal lookup fields, hidden detail/content, unsupported contexts, approval-gated assignment and inactive selected IDs.
- tests/reference_data.test.mjs verifies real-count calculations and empty-state handling.
- tests/reference_browser.py exercises the real login form, bundled assets, reveal toggle, username-only remembrance, theme persistence, profile, mobile navigation and sign-out in a disposable MySQL environment.
- Existing plain/styled, controller, concurrency and visibility tests remain in CI.

Local design screenshots use synthetic demonstration data, not live company records. Changes are developed on a feature branch; the user's running XAMPP installation is not modified by committing repository code.
