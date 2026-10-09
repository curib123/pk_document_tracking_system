<?php
// Focused login UI contract, independent of database setup or credentials.
$root=dirname(__DIR__);
$view=file_get_contents($root.'/application/view/pages/authentication/index.php');
$css=file_get_contents($root.'/public/assets/css/app.css');
$checks=[
  'existing cover photograph is the page background' =>
      str_contains($view, 'class="login-page"') &&
      str_contains($view, "assets/images/building.jpg"),
  'welcome text and form are in one centered shell' =>
      str_contains($view, 'class="login-cover"') &&
      str_contains($view, 'class="login-panel"') &&
      str_contains($view, 'class="login-card"'),
  'cover image fills the viewport' =>
      (bool) preg_match('/\\.login-page\\s*\\{[^}]*background-size:\\s*cover;/s', $css),
  'text and white form are inline and centered on desktop' =>
      (bool) preg_match('/\\.login-shell\\s*\\{[^}]*grid-template-columns:[^;]+;[^}]*align-items:\\s*center;/s', $css) &&
      (bool) preg_match('/\\.login-card\\s*\\{[^}]*background:\\s*#fff;/s', $css),
  'mobile layout stacks and remains centered' =>
      str_contains($css, 'grid-template-columns: minmax(0, 1fr);') &&
      str_contains($css, 'justify-items: center;'),
  'login form remains POST with CSRF' =>
      str_contains($view, 'method="post"') &&
      str_contains($view, "site_url('login')") &&
      str_contains($view, 'get_csrf_token_name()') &&
      str_contains($view, 'get_csrf_hash()'),
  'login fields and user feedback remain present' =>
      str_contains($view, 'name="login"') &&
      str_contains($view, 'name="password"') &&
      str_contains($view, "flashdata('notice')")
];
$failed=array_keys(array_filter($checks,static fn($ok)=>!$ok));
if ($failed) {
    foreach ($failed as $message) fwrite(STDERR,"FAIL: $message\\n");
    exit(1);
}
echo "Login layout checks passed (".count($checks)." checks).\\n";
