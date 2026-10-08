<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central icon map: Font Awesome -> Bootstrap Icons -> offline text symbol.
 * Every user-visible label remains real text outside the icon.
 */
if (!function_exists('pk_web_icon')) {
    function pk_web_icon(string $name): string
    {
        $icons = [
            'dashboard' => ['gauge-high', 'speedometer2', '◉'],
            'softcopy' => ['file-lines', 'file-earmark-text', '▤'],
            'hardcopy' => ['folder-open', 'folder2-open', '▣'],
            'requests' => ['clipboard-check', 'clipboard-check', '✓'],
            'my_requests' => ['paper-plane', 'send', '↗'],
            'my_tasks' => ['list-check', 'check2-square', '☑'],
            'transfers' => ['right-left', 'arrow-left-right', '⇄'],
            'access' => ['lock-open', 'unlock', '◇'],
            'assignments' => ['user-check', 'person-check', '✓'],
            'disposals' => ['box-archive', 'archive', '▧'],
            'files' => ['paperclip', 'paperclip', '⌁'],
            'workflows' => ['diagram-project', 'diagram-3', '◇'],
            'users' => ['users', 'people', '♙'],
            'roles' => ['shield-halved', 'shield-lock', '◆'],
            'permissions' => ['key', 'key', '⌁'],
            'areas' => ['layer-group', 'layers', '▤'],
            'specifics' => ['location-dot', 'geo-alt', '⌖'],
            'assets' => ['boxes-stacked', 'boxes', '▦'],
            'locations' => ['map-location-dot', 'map', '⌖'],
            'categories' => ['tags', 'tags', '◇'],
            'audit' => ['clock-rotate-left', 'clock-history', '↺'],
            'history' => ['clock', 'clock', '◷'],
            'notifications' => ['bell', 'bell', '◉'],
            'settings' => ['gear', 'gear', '⚙'],
            'sequences' => ['hashtag', 'hash', '#'],
            'search' => ['magnifying-glass', 'search', '⌕'],
            'menu' => ['bars', 'list', '☰'],
            'close' => ['xmark', 'x-lg', '×'],
            'chevron' => ['chevron-down', 'chevron-down', '⌄'],
            'add' => ['plus', 'plus', '+'],
            'edit' => ['pen-to-square', 'pencil-square', '✎'],
            'delete' => ['trash-can', 'trash', '×'],
            'view' => ['eye', 'eye', '◉'],
            'warning' => ['triangle-exclamation', 'exclamation-triangle', '!'],
            'success' => ['circle-check', 'check-circle', '✓'],
            'info' => ['circle-info', 'info-circle', 'i'],
            'logout' => ['right-from-bracket', 'box-arrow-right', '↗'],
            'filter' => ['filter', 'funnel', '▽'],
            'reset' => ['rotate-left', 'arrow-counterclockwise', '↺'],
        ];
        [$fa, $bi, $glyph] = $icons[$name] ?? ['circle', 'circle', '•'];

        return '<span class="pk-icon" aria-hidden="true">'
            . '<i class="fa-solid fa-' . $fa . ' pk-fa"></i>'
            . '<i class="bi bi-' . $bi . ' pk-bi"></i>'
            . '<span class="pk-icon-fallback">' . htmlspecialchars($glyph, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }
}

if (!function_exists('pk_web_route')) {
    function pk_web_route(string $current, string $target): bool
    {
        $current = trim($current, '/');
        return $current === $target
            || str_starts_with($current, $target . '/')
            || ($target === 'web' && $current === '');
    }
}
