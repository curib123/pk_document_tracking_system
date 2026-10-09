"""Native CI3 HTML-session and permission regression (disposable MySQL only)."""
import http.cookiejar
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

assert os.environ.get("PK_TEST_DB") == "1"
assert os.environ.get("DB_DATABASE", "").endswith("_test")
base = os.environ["APP_URL"].rstrip("/") + "/index.php/"
password = os.environ["CI_ROUTE_PASSWORD"]
checks = 0


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.open = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies)
        )

    def request(self, route, data=None):
        url = base + route
        if data is not None:
            data = urllib.parse.urlencode(data).encode()
        request = urllib.request.Request(
            url, data=data,
            headers={"Accept": "text/html",
                     "Content-Type": "application/x-www-form-urlencoded"},
        )
        try:
            response = self.open.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode("utf-8", errors="replace")

    def sign_in(self, username, password):
        code, html = self.request("web/login")
        assert code == 200, (code, html[:500])
        match = re.search(r'name="csrf"\s+value="([0-9a-f]{64})"', html)
        assert match, "Native sign-in form must include a CI session CSRF token."
        code, html = self.request("web/sign-in", {
            "csrf": match.group(1),
            "username": username,
            "password": password,
        })
        assert code == 200 and "Workspace overview" in html, (code, html[:700])
        assert any(c.name == "pk_dts_ci_session" for c in self.cookies)
        return html


admin = Browser()
code, page = admin.request("web/catalog/areas")
assert code == 200 and "Sign in" in page, (code, page[:700])
checks += 1

code, login = admin.request("web/login")
assert code == 200
code, invalid = admin.request("web/sign-in", {
    "csrf": "0" * 64, "username": "admin", "password": password
})
assert code == 419 and "form expired" in invalid.lower(), (code, invalid[:700])
checks += 1

admin.sign_in("admin", password)
checks += 1

for route, marker in [
    ("web", "Workspace overview"),
    ("web/catalog/areas", "Areas"),
    ("web/records/users", "Users"),
]:
    code, html = admin.request(route)
    assert code == 200 and marker in html, (route, code, html[:700])
    checks += 1

# The existing CI test creates a Staff user and completes its password reset
# before calling this test. This checks the exact server-side role boundaries.
query = (
    'require "application/bootstrap.php";'
    '$db=\\Pk\\Core\\Database::connect();'
    '$row=$db->one("SELECT username FROM users WHERE username LIKE \'route_%\' '
    'ORDER BY id DESC LIMIT 1");'
    'echo $row["username"] ?? "";'
)
username = subprocess.check_output(
    ["php", "-r", query],
    cwd=os.path.dirname(os.path.dirname(__file__)),
    text=True,
).strip()
assert username, "Run tests/native_routes.py before this suite."

staff = Browser()
staff.sign_in(username, password)
checks += 1

for route in [
    "web/catalog/areas",
    "web/catalog/areas/new",
    "web/records/users",
]:
    code, html = staff.request(route)
    assert code == 403 and "permission" in html.lower(), (route, code, html[:700])
    checks += 1

code, html = staff.request("web")
assert code == 200 and "Workspace overview" in html
checks += 1

# A logout clears the CI session and cannot leave protected routes readable.
csrf = re.search(r'name="csrf"\s+value="([0-9a-f]{64})"', html)
assert csrf, "Dashboard sign-out must contain a CSRF token."
code, html = staff.request("web/logout", {"csrf": csrf.group(1)})
assert code == 200 and "Sign in" in html
code, html = staff.request("web")
assert code == 200 and "Sign in" in html
checks += 2

print(f"PASS {checks} CI3 session/login/CSRF/permission HTTP checks")
