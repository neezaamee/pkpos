<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminMigrationController extends Controller
{
    /**
     * Display the database migrations and schema maintenance dashboard.
     */
    public function index(Request $request): Response
    {
        $connectionName = config('database.default', 'pgsql');
        $dbConfig = config("database.connections.{$connectionName}", []);

        // Connection status check
        $dbStatus = 'disconnected';
        $dbError = null;
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        // Get all migration files from database/migrations
        $migrationPath = database_path('migrations');
        $files = File::exists($migrationPath) ? File::files($migrationPath) : [];
        $fileNames = collect($files)->map(function ($f) {
            return pathinfo($f->getFilename(), PATHINFO_FILENAME);
        })->sort()->values()->all();

        // Get executed migrations from the 'migrations' table
        $ranMigrations = [];
        $ranNames = [];
        if ($dbStatus === 'connected' && Schema::hasTable('migrations')) {
            try {
                $ranMigrations = DB::table('migrations')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'migration' => $r->migration,
                        'batch' => $r->batch,
                    ])
                    ->all();
                $ranNames = array_column($ranMigrations, 'migration');
            } catch (\Throwable) {
                $ranMigrations = [];
                $ranNames = [];
            }
        }

        // Determine pending migrations
        $pendingMigrations = array_values(array_diff($fileNames, $ranNames));

        // Master data statistics
        $masterStats = [
            'plans' => Schema::hasTable('plans') ? \App\Models\Plan::count() : 0,
            'roles' => Schema::hasTable('roles') ? \App\Models\Role::count() : 0,
            'hs_codes' => Schema::hasTable('master_hs_codes') ? \App\Models\MasterHsCode::count() : 0,
            'tax_templates' => Schema::hasTable('provincial_tax_templates') ? \App\Models\ProvincialTaxTemplate::count() : 0,
            'articles' => Schema::hasTable('knowledge_base_articles') ? \App\Models\KnowledgeBaseArticle::count() : 0,
            'settings' => Schema::hasTable('system_settings') ? \App\Models\SystemSetting::count() : 0,
        ];

        return Inertia::render('SuperAdmin/System/Migrations', [
            'database' => [
                'connection' => $connectionName,
                'driver' => $dbConfig['driver'] ?? 'pgsql',
                'database' => $dbConfig['database'] ?? 'pk_pos',
                'host' => $dbConfig['host'] ?? '127.0.0.1',
                'status' => $dbStatus,
                'error' => $dbError,
            ],
            'stats' => [
                'total_files' => count($fileNames),
                'ran_count' => count($ranNames),
                'pending_count' => count($pendingMigrations),
                'latest_batch' => count($ranMigrations) > 0 ? (int) $ranMigrations[0]['batch'] : 0,
            ],
            'master_stats' => $masterStats,
            'pending_migrations' => $pendingMigrations,
            'ran_migrations' => array_slice($ranMigrations, 0, 100),
            'last_output' => $request->session()->get('migration_output'),
        ]);
    }

    /**
     * Seed or re-synchronize production-safe platform master data.
     */
    public function seedMaster(Request $request): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\PlatformMasterSeeder',
                '--force' => true,
            ]);
            $output = trim(Artisan::output());

            try {
                AuditLog::create([
                    'tenant_id' => null,
                    'user_id' => Auth::id(),
                    'event' => 'platform_master_data_seeded',
                    'auditable_type' => 'App\Models\PlatformMasterData',
                    'auditable_id' => '00000000-0000-0000-0000-000000000000',
                    'new_values' => ['exit_code' => $exitCode, 'output' => substr($output, 0, 2000)],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable) {}

            return back()
                ->with('success', 'Platform master data (Subscription Plans, Staff Roles, HS Codes, Tax Templates, System Settings) seeded successfully!')
                ->with('migration_output', $output ?: 'Platform master data initialized successfully.');
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Seeding failed: ' . $e->getMessage())
                ->with('migration_output', "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * Run all pending database migrations via Artisan migrate --force.
     */
    public function run(Request $request): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            try {
                AuditLog::create([
                    'tenant_id' => null,
                    'user_id' => Auth::id(),
                    'event' => 'database_migrations_executed',
                    'auditable_type' => 'App\Models\DatabaseMigration',
                    'auditable_id' => '00000000-0000-0000-0000-000000000000',
                    'new_values' => ['exit_code' => $exitCode, 'output' => substr($output, 0, 2000)],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable) {}

            return back()
                ->with('success', 'Pending migrations executed successfully!')
                ->with('migration_output', $output ?: 'Nothing to migrate. Database schema is already up to date.');
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Migration failed: ' . $e->getMessage())
                ->with('migration_output', "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * Rollback the most recent migration batch.
     */
    public function rollback(Request $request): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
            $output = trim(Artisan::output());

            try {
                AuditLog::create([
                    'tenant_id' => null,
                    'user_id' => Auth::id(),
                    'event' => 'database_migrations_rollback',
                    'auditable_type' => 'App\Models\DatabaseMigration',
                    'auditable_id' => '00000000-0000-0000-0000-000000000000',
                    'new_values' => ['exit_code' => $exitCode, 'output' => substr($output, 0, 2000)],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable) {}

            return back()
                ->with('success', 'Latest migration batch rolled back successfully.')
                ->with('migration_output', $output ?: 'Rollback completed.');
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Rollback failed: ' . $e->getMessage())
                ->with('migration_output', "ERROR: " . $e->getMessage());
        }
    }

    /**
     * Clear and rebuild application, route, config, and view caches.
     */
    public function optimizeClear(Request $request): RedirectResponse
    {
        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());

            return back()
                ->with('success', 'Application caches cleared and optimized.')
                ->with('migration_output', $output);
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Optimization failed: ' . $e->getMessage())
                ->with('migration_output', "ERROR: " . $e->getMessage());
        }
    }
}
