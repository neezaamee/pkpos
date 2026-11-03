<?php

use App\Models\AuditLog;
use App\Models\FiscalRetentionArchive;
use App\Models\PlatformBackup;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
        'staff_role' => 'super_admin',
    ]);

    $this->complianceAuditor = User::factory()->superAdmin()->create([
        'email' => 'auditor@example.com',
        'staff_role' => 'compliance_auditor',
    ]);

    $this->releaseEngineer = User::factory()->superAdmin()->create([
        'email' => 'devops@example.com',
        'staff_role' => 'release_engineer',
    ]);

    $this->regularUser = User::factory()->create([
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Imtiaz Super Market',
        'province' => 'sindh',
        'ntn' => '4928172-1',
    ]);
});

test('non-staff user cannot access backups and fiscal retention dashboard', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.backups.index'))
        ->assertForbidden();
});

test('super admin, compliance auditor, and devops can view backups dashboard with metrics', function () {
    PlatformBackup::create([
        'filename' => 'pkpos-test-snapshot.sql.gz',
        'backup_type' => 'automated_daily',
        'status' => 'completed',
        'file_size_bytes' => 25000000,
        'sha256_checksum' => 'testchecksum12345678',
    ]);

    FiscalRetentionArchive::create([
        'archive_code' => 'ARC-FY2526-0001',
        'tenant_id' => $this->tenant->id,
        'financial_year' => '2025-2026',
        'record_count' => 1500,
        'file_size_bytes' => 1500000,
        'statutory_retention_until' => now()->addYears(6)->toDateString(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.backups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Backups/Index')
            ->has('backups.data', 1)
            ->has('fiscalArchives.data', 1)
            ->where('metrics.total_backups_count', 1)
            ->where('metrics.fiscal_archives_count', 1)
            ->where('metrics.statutory_locked_records', 1500)
        );

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.backups.index'))
        ->assertOk();

    $this->actingAs($this->releaseEngineer)
        ->get(route('superadmin.backups.index'))
        ->assertOk();
});

test('super admin can trigger on-demand PostgreSQL database snapshot and it logs audit trail', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.backups.snapshot'), [
            'notes' => 'Pre-deployment database snapshot before sprint release.',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('platform_backups', [
        'backup_type' => 'manual_superadmin',
        'status' => 'completed',
        'created_by' => $this->superAdmin->id,
        'notes' => 'Pre-deployment database snapshot before sprint release.',
    ]);

    $backup = PlatformBackup::latest()->first();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'database_backup_created',
        'auditable_type' => PlatformBackup::class,
        'auditable_id' => $backup->id,
    ]);
});

test('super admin can compile 6-year statutory fiscal archive for tenant with Section 22 compliance', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.backups.fiscal-archive'), [
            'tenant_id' => $this->tenant->id,
            'financial_year' => '2025-2026',
        ]);

    $response->assertRedirect();

    $archive = FiscalRetentionArchive::where('tenant_id', $this->tenant->id)->first();
    expect($archive)->not->toBeNull();
    expect($archive->financial_year)->toBe('2025-2026');
    expect($archive->archive_code)->toStartWith('ARC-FY2526-');

    // Verify statutory 6-year retention date
    $expectedRetentionDate = now()->addYears(6)->toDateString();
    expect($archive->statutory_retention_until->toDateString())->toBe($expectedRetentionDate);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'fiscal_retention_archive_generated',
        'auditable_type' => FiscalRetentionArchive::class,
        'auditable_id' => $archive->id,
    ]);
});

test('staff can download fiscal retention archive package', function () {
    $archive = FiscalRetentionArchive::create([
        'archive_code' => 'ARC-FY2425-0099',
        'tenant_id' => $this->tenant->id,
        'financial_year' => '2024-2025',
        'record_count' => 500,
        'file_size_bytes' => 500000,
        'sha256_checksum' => 'checksum99999',
        'statutory_retention_until' => now()->addYears(6)->toDateString(),
    ]);

    $response = $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.backups.fiscal-archive.download', $archive->id));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
});

test('super admin can delete backup snapshot with audit trail', function () {
    $backup = PlatformBackup::create([
        'filename' => 'obsolete-snapshot.sql.gz',
        'backup_type' => 'manual_superadmin',
        'status' => 'completed',
        'file_size_bytes' => 10000,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.backups.destroy', $backup->id));

    $response->assertRedirect();

    $this->assertDatabaseMissing('platform_backups', [
        'id' => $backup->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'database_backup_deleted',
        'auditable_type' => PlatformBackup::class,
        'auditable_id' => $backup->id,
    ]);
});

