<?php
$root = dirname(__DIR__);
$directories = ['application', 'public', 'tools', 'tests'];
$errors = 0;
$files = 0;
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $files++;
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
        exec($command, $output, $status);
        if ($status !== 0) {
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
            $errors++;
        }
        $output = [];
    }
}
if ($errors) exit(1);
echo "PHP syntax passed for " . $files . " files." . PHP_EOL;
