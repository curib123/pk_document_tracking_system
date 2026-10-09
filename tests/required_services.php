<?php
$root = dirname(__DIR__);
$missing = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/application')) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    preg_match_all("/require_once\\s+APPPATH\\s*\\.\\s*['\"]([^'\"]+)['\"]/", file_get_contents($file->getPathname()), $matches);
    foreach ($matches[1] as $path) {
        if (!is_file($root.'/application/'.$path) || !filesize($root.'/application/'.$path)) $missing[] = $path;
    }
}
if ($missing) {fwrite(STDERR, 'Missing required services: '.implode(', ', array_unique($missing)).PHP_EOL); exit(1);}
echo "All required application service files exist.\n";
