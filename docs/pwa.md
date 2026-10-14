# Install PK DTS once

On supported Chrome/Edge browsers, **Install App** appears in the top navigation when the browser offers installation. Choose it and accept the browser's installation dialog. The button hides after acceptance, an `appinstalled` event, or launch in the installed standalone window. Eligible prompts are consumed once. A genuine browser prompt after uninstalling clears an obsolete local installation flag.

The public manifest has a stable app ID, launch URL and scope for both root and subdirectory deployments. Keep the site origin and deployment path stable; do not add release/version numbers to the manifest ID. Future code, layout, permission or workflow changes do not require another installation. The PWA runs the same current CodeIgniter application and uses its existing sessions and permissions.

The worker fetches application GETs from the network with `cache: no-store`, leaves POST forms untouched, and never puts documents or authenticated pages in CacheStorage. A navigation or refresh picks up changed application assets and server logic. Registration checks for worker updates on page load, returning to the page and reconnecting. Worker activation does not forcibly reload an open form. Without a connection, the app asks the user to reconnect and refresh.

PWA installation requires HTTPS or localhost. Custom installation events are browser-dependent; unsupported browsers keep the button hidden and may provide their own native installation UI. See [MDN installation requirements](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable) and [custom installation controls](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Trigger_install_prompt).

## Checks

- `php tests/pwa_manifest.php`: stable IDs and correct URLs at both deployment scopes; actual 192/512 PNG dimensions.
- `node --test tests/pwa.test.js`: install eligibility, accepted/dismissed prompts, standalone state, delayed installation notifications, network freshness and untouched POST requests.
- `python tests/browser_document_workflows.py`: uses a temporary regular Edge profile and isolated application storage/database; checks Chrome's manifest/installability result, live worker control, empty CacheStorage and a simulated top-nav prompt. It also exercises the document workflows under that worker. Native OS installation and physical printer output are not automated.

Rebuild the brand icons on Windows with `powershell -File tools/build_pwa_icons.ps1` if the source brand image changes. Leave the manifest identity unchanged.
