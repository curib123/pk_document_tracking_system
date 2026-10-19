"""Exercise real Apache rewrites with an owned static fixture, without any DB access."""
import json
import shutil
import tempfile
from pathlib import Path
from urllib.request import urlopen, build_opener, HTTPRedirectHandler
from urllib.error import HTTPError

ROOT = Path(__file__).resolve().parents[1]
HTDOCS = Path('C:/xampp/htdocs').resolve()
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args): return None

with tempfile.TemporaryDirectory(prefix='pk-dts-routing-', dir=HTDOCS) as owned:
    site = Path(owned).resolve()
    assert site.parent == HTDOCS and site.name.startswith('pk-dts-routing-')
    shutil.copyfile(ROOT / '.htaccess', site / '.htaccess')
    (site / 'index.php').write_text('<?php header("Content-Type: application/json"); echo json_encode(["script"=>$_SERVER["SCRIPT_NAME"],"uri"=>$_SERVER["REQUEST_URI"]]);', encoding='utf-8')
    (site / 'public/assets').mkdir(parents=True)
    shutil.copyfile(ROOT / 'public/.htaccess', site / 'public/.htaccess')
    (site / 'public/assets/probe.txt').write_text('public asset', encoding='utf-8')
    (site / 'application').mkdir()
    (site / 'application/private.txt').write_text('private', encoding='utf-8')
    (site / 'SYSTEM_MAP.md').write_text('private docs', encoding='utf-8')
    (site / 'SYSTEM_BUSINESS_LOGIC.mmd').write_text('flowchart TB', encoding='utf-8')
    (site / 'composer.json').write_text('{}', encoding='utf-8')
    shutil.copyfile(ROOT / 'public/manifest.php', site / 'public/manifest.php')
    base = 'http://localhost/' + site.name + '/'
    assert json.load(urlopen(base + 'login'))['script'] == '/' + site.name + '/index.php'
    assert urlopen(base + 'assets/probe.txt').read() == b'public asset'
    manifest = json.load(urlopen(base + 'manifest.php'))
    assert manifest['start_url'] == '/' + site.name + '/dashboard', manifest
    assert manifest['id'] == '/' + site.name + '/public/pk-document-control', manifest
    try: build_opener(NoRedirect).open(base + 'public/login?next=dashboard')
    except HTTPError as response:
        assert response.code == 308 and response.headers['Location'] == base + 'login?next=dashboard', response.headers
    else: raise AssertionError('Legacy URL did not redirect')
    for path in ['application/private.txt', 'SYSTEM_MAP.md', 'SYSTEM_BUSINESS_LOGIC.mmd', 'composer.json', '.git/config']:
        try: urlopen(base + path)
        except HTTPError as response: assert response.code == 403, (path, response.code)
        else: raise AssertionError('Private path was exposed: ' + path)
print('Apache clean routes, legacy redirect/query, assets, PWA identity/scope and private path protection passed.')
