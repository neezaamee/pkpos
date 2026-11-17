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

// Bootstrap Laravel and execute automated database migrations
$migrationOutput = "SKIPPED";
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/bootstrap/app.php')) {
        require_once __DIR__ . '/vendor/autoload.php';

        if (file_exists(__DIR__ . '/.env')) {
            $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && !str_starts_with($line, '#') && str_contains($line, '=')) {
                    putenv($line);
                    [$k, $v] = explode('=', $line, 2);
                    $v = trim($v, "\"'");
                    $_ENV[trim($k)] = $v;
                    $_SERVER[trim($k)] = $v;
                }
            }
        }

        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateLog = trim(\Illuminate\Support\Facades\Artisan::output());

        // Automatically sync production-safe platform master data
        \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\PlatformMasterSeeder',
            '--force' => true,
        ]);
        $seedLog = trim(\Illuminate\Support\Facades\Artisan::output());

        $migrationOutput = "=== MIGRATIONS ===\n" . ($migrateLog ?: "Schema is up to date.") . "\n\n=== MASTER SEEDING ===\n" . ($seedLog ?: "Platform master data synchronized.");
    }
} catch (\Throwable $e) {
    $migrationOutput = "MIGRATION_FAILED: " . $e->getMessage();
}

@unlink($zipPath);
@unlink($tokenFile);
@unlink(__FILE__);

echo "SUCCESS: Payload extracted and cleaned up.\n";
echo "MIGRATION_LOG:\n" . $migrationOutput . "\n";
