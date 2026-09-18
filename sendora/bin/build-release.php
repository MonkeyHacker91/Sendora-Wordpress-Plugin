<?php
/**
 * Build a WordPress.org-ready sendora.zip from the plugin directory.
 *
 * Usage: php bin/build-release.php
 *
 * @package Sendora
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginDir = $root;
$outZip = dirname($root) . DIRECTORY_SEPARATOR . 'sendora.zip';

$excludeNames = [
    '.git',
    '.gitattributes',
    '.gitignore',
    '.distignore',
    '.github',
    '.superpowers',
    '.phpunit.cache',
    'composer.json',
    'composer.lock',
    'phpunit.xml.dist',
    'vendor',
    'tests',
    'docs',
    'bin',
    'node_modules',
];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension is required.\n");
    exit(1);
}

if (is_file($outZip)) {
    unlink($outZip);
}

$zip = new ZipArchive();
if ($zip->open($outZip, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Unable to create {$outZip}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }

    $absolute = $file->getPathname();
    $relative = substr($absolute, strlen($pluginDir) + 1);
    $relative = str_replace('\\', '/', $relative);
    $parts = explode('/', $relative);

    if (in_array($parts[0], $excludeNames, true)) {
        continue;
    }

    if (str_ends_with(strtolower($relative), '.md')) {
        continue;
    }

    $zip->addFile($absolute, 'sendora/' . $relative);
}

$zip->close();

echo "Built: {$outZip}\n";
echo 'Size: ' . filesize($outZip) . " bytes\n";
