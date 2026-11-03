<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Retail Mart',
        'province' => 'punjab',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-super admin cannot access system telemetry endpoints', function () {
    $this->actingAs($this->owner)
        ->get(route('superadmin.system.health'))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->get(route('superadmin.system.jobs'))
        ->assertForbidden();
});

test('super admin can view system health dashboard with database telemetry', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.system.health'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/System/Health')
            ->has('database.driver')
            ->has('database.latency_ms')
            ->has('database.tables')
            ->has('runtime.php_version')
            ->has('runtime.memory_usage')
        );
});

test('super admin can view background jobs queue monitor', function () {
    // Insert a dummy failed job
    $failedJobId = DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\Jobs\SendTenantWelcomeEmail',
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        ]),
        'exception' => "Connection refused to mailhost:587\n#0 [internal function]...",
        'failed_at' => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.system.jobs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/System/Jobs')
            ->has('failed_jobs.data', 1)
            ->where('failed_jobs.data.0.id', $failedJobId)
            ->where('failed_jobs.data.0.display_name', 'App\Jobs\SendTenantWelcomeEmail')
            ->where('metrics.failed_jobs_count', 1)
        );
});

test('super admin can retry a single failed job', function () {
    $failedJobId = DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\Jobs\SyncSalesBatch']),
        'exception' => 'TimeoutException: Gateway timed out',
        'failed_at' => now(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.system.jobs.retry', $failedJobId));

    $response->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue_job_retried',
        'auditable_type' => 'FailedJob',
    ]);
});

test('super admin can retry all failed jobs in bulk', function () {
    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Job 1']),
            'exception' => 'Error 1',
            'failed_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Job 2']),
            'exception' => 'Error 2',
            'failed_at' => now(),
        ],
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.system.jobs.retry-all'));

    $response->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue_all_jobs_retried',
        'auditable_type' => 'FailedJob',
    ]);
});

test('super admin can delete a single failed job', function () {
    $failedJobId = DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'Obsolete Job']),
        'exception' => 'Fatal Error',
        'failed_at' => now(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.system.jobs.delete', $failedJobId));

    $response->assertRedirect();

    expect(DB::table('failed_jobs')->where('id', $failedJobId)->exists())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue_job_deleted',
        'auditable_type' => 'FailedJob',
    ]);
});

test('super admin can flush all failed jobs', function () {
    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Job A']),
            'exception' => 'Error A',
            'failed_at' => now(),
        ],
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.system.jobs.flush'));

    $response->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue_all_jobs_flushed',
        'auditable_type' => 'FailedJob',
    ]);
});

