<?php
/** Shared unstyled view helpers. Never print raw user values into HTML. */
if (!function_exists('ui_escape')) {
    function ui_escape(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    function ui_url(string $path=''): string { return rtrim(getenv('APP_URL') ?: 'http://localhost:8080','/').'/'.ltrim($path,'/'); }
}
