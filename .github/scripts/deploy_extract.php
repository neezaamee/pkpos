<?php
/**
 * PK-POS Deployment Self-Extractor
 * Safely unpacks deploy_payload.zip and cleans up temporary deploy files.
 */
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$tokenFile = __DIR__ . '/.deploy_token';

if (!file_exists($tokenFile) || empty($token)) {
    http_response_code(403);
    die("FORBIDDEN: Token file not found.\n");
}

$expectedToken = trim(file_get_contents($tokenFile));
if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    die("FORBIDDEN: Invalid deployment token.\n");
}

$zipPath = __DIR__ . '/deploy_payload.zip';
if (!file_exists($zipPath)) {
    http_response_code(404);
    die("NOT_FOUND: deploy_payload.zip missing.\n");
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die("ERROR: ZipArchive extension unavailable.\n");
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    die("ERROR: Unable to open deploy_payload.zip.\n");
}

$zip->extractTo(__DIR__);
$zip->close();

$writableDirs = [
    __DIR__ . '/storage/app/public',
    __DIR__ . '/storage/framework/cache/data',
    __DIR__ . '/storage/framework/sessions',
    __DIR__ . '/storage/framework/views',
    __DIR__ . '/storage/logs',
    __DIR__ . '/bootstrap/cache',
];
foreach ($writableDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    } else {
        @chmod($dir, 0775);
    }
}

@unlink($zipPath);
@unlink($tokenFile);
@unlink(__FILE__);

echo "SUCCESS: Payload extracted and cleaned up.\n";
