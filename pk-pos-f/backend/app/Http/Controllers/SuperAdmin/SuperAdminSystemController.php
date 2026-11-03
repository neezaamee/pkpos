<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminSystemController extends Controller
{
    /**
     * Display database telemetry, infrastructure health, and transaction throughput.
     */
    public function health(): Response
    {
        // 1. Database Connectivity & Benchmark Latency
        $start = microtime(true);
        DB::select('SELECT 1');
        $latencyMs = round((microtime(true) - $start) * 1000, 2);

        $driver = DB::getDriverName();
        $dbName = DB::connection()->getDatabaseName();
        $dbSize = 'N/A';

        if ($driver === 'pgsql') {
            try {
                $sizeRes = DB::select("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
                $dbSize = $sizeRes[0]->size ?? 'N/A';
            } catch (\Throwable) {
                $dbSize = 'N/A';
            }
        }

        // 2. Table row counts
        $tablesToCheck = [
            'tenants' => 'Tenants (Merchants)',
            'users' => 'Users & Staff',
            'locations' => 'Branch Locations',
            'sales' => 'Completed Sales',
            'sale_items' => 'Line Items',
            'stock_movements' => 'Stock Ledger',
            'audit_logs' => 'Audit Trail',
            'pos_terminals' => 'Hardware Fleet',
            'releases' => 'Releases & OTA',
            'support_tickets' => 'Helpdesk Tickets',
            'failed_jobs' => 'Failed Jobs',
        ];

        $tableStats = [];
        foreach ($tablesToCheck as $tbl => $label) {
            try {
                $count = DB::table($tbl)->count();
                $tableStats[] = [
                    'table' => $tbl,
                    'label' => $label,
                    'rows' => $count,
                ];
            } catch (\Throwable) {
                // Ignore if table does not exist
            }
        }

        // 3. Runtime environment
        $runtime = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'os' => PHP_OS_FAMILY,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
        ];

        // 4. Hourly Sales Sync Throughput (Last 24 Hours)
        $hourlyThroughput = [];
        try {
            $twentyFourHoursAgo = now()->subHours(24);
            $recentSales = Sale::withoutGlobalScopes()
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->get(['created_at']);

            $grouped = $recentSales->groupBy(fn ($s) => $s->created_at->format('Y-m-d H:00'));
            foreach ($grouped as $hour => $sales) {
                $hourlyThroughput[] = [
                    'hour' => $hour,
                    'count' => $sales->count(),
                ];
            }
        } catch (\Throwable) {
            $hourlyThroughput = [];
        }

        return Inertia::render('SuperAdmin/System/Health', [
            'database' => [
                'driver' => $driver,
                'name' => $dbName,
                'latency_ms' => $latencyMs,
                'size' => $dbSize,
                'tables' => $tableStats,
            ],
            'runtime' => $runtime,
            'sync_throughput' => $hourlyThroughput,
        ]);
    }

    /**
     * Display background queue status, workers, and failed jobs monitor.
     */
    public function jobs(Request $request): Response
    {
        $failedJobsQuery = DB::table('failed_jobs')->latest('failed_at');

        $failedJobs = $failedJobsQuery->paginate(15)
            ->withQueryString()
            ->through(function ($job) {
                $displayName = 'Unknown Job';
                try {
                    $payload = json_decode($job->payload, true);
                    $displayName = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown Job');
                } catch (\Throwable) {
                    $displayName = 'Unparseable Job Payload';
                }

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'display_name' => $displayName,
                    'exception_snippet' => substr($job->exception, 0, 200) . '...',
                    'exception_full' => $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            });

        $pendingJobsCount = 0;
        try {
            $pendingJobsCount = DB::table('jobs')->count();
        } catch (\Throwable) {
            $pendingJobsCount = 0;
        }

        $failedJobsCount = DB::table('failed_jobs')->count();

        return Inertia::render('SuperAdmin/System/Jobs', [
            'failed_jobs' => $failedJobs,
            'metrics' => [
                'pending_jobs_count' => $pendingJobsCount,
                'failed_jobs_count' => $failedJobsCount,
            ],
        ]);
    }

    /**
     * Retry a single failed background job.
     */
    public function retryJob(Request $request, string $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')
            ->where('id', is_numeric($id) ? (int) $id : 0)
            ->orWhere('uuid', $id)
            ->first();

        if (! $job) {
            return back()->with('error', "Failed job #{$id} not found.");
        }

        $identifier = $job->uuid ?: $job->id;

        try {
            Artisan::call('queue:retry', ['id' => [(string) $identifier]]);
        } catch (\Throwable $e) {
            // Log or ignore if artisan runner encountered driver nuance
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'queue_job_retried',
            'auditable_type' => 'FailedJob',
            'auditable_id' => $job->uuid ?: (string) Str::uuid(),
            'new_values' => ['job_id' => $id, 'uuid' => $job->uuid],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Failed job #{$id} queued for retry.");
    }

    /**
     * Bulk retry all failed background jobs.
     */
    public function retryAllJobs(Request $request): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (\Throwable $e) {
            // Graceful handling
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'queue_all_jobs_retried',
            'auditable_type' => 'FailedJob',
            'auditable_id' => (string) Str::uuid(),
            'new_values' => ['action' => 'retry_all'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'All failed background jobs queued for retry.');
    }

    /**
     * Delete/purge a single failed job record.
     */
    public function deleteJob(Request $request, string $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')
            ->where('id', is_numeric($id) ? (int) $id : 0)
            ->orWhere('uuid', $id)
            ->first();

        $jobUuid = $job ? $job->uuid : (string) Str::uuid();

        DB::table('failed_jobs')
            ->where('id', is_numeric($id) ? (int) $id : 0)
            ->orWhere('uuid', $id)
            ->delete();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'queue_job_deleted',
            'auditable_type' => 'FailedJob',
            'auditable_id' => $jobUuid,
            'new_values' => ['job_id' => $id, 'uuid' => $jobUuid],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Failed job #{$id} deleted.");
    }

    /**
     * Flush all failed jobs from the failed_jobs queue table.
     */
    public function flushJobs(Request $request): RedirectResponse
    {
        $deletedCount = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->delete();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'queue_all_jobs_flushed',
            'auditable_type' => 'FailedJob',
            'auditable_id' => (string) Str::uuid(),
            'new_values' => ['action' => 'flush_all', 'deleted_count' => $deletedCount],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'All failed background jobs have been flushed.');
    }
}
