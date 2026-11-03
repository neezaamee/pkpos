<?php

use App\Models\AuditLog;
use App\Models\Release;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->regularUser = User::factory()->create([
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-super admin cannot access superadmin releases index', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.releases.index'))
        ->assertForbidden();
});

test('super admin can view releases dashboard with fleet version metrics', function () {
    Release::create([
        'version' => '0.2.0',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'title' => 'FBR SRO Compliance v0.2.0',
        'summary' => 'Provincial tax calculation and desktop OTA update support.',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.releases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Releases/Index')
            ->has('releases.data')
            ->where('metrics.latest_desktop_version', '0.2.0')
            ->has('metrics.total_releases')
        );
});

test('super admin can create a new release package', function () {
    $payload = [
        'version' => '0.3.0',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'tauri_target' => 'windows-x86_64',
        'title' => 'Enhanced Thermal Receipt Printing',
        'summary' => 'Direct ESC/POS hardware thermal printer acceleration.',
        'changelog_markdown' => "### Features\n- ESC/POS direct USB printing\n\n### Bug Fixes\n- Paper cutter command fix",
        'download_url' => 'https://releases.pkpos.pk/downloads/pk-pos-0.3.0.msi',
        'signature' => 'SIG_TEST_ABC123',
        'sha256_checksum' => 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
        'min_supported_version' => '0.2.0',
        'is_critical' => true,
        'is_published' => true,
    ];

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.releases.store'), $payload)
        ->assertRedirect();

    $release = Release::where('version', '0.3.0')->first();
    expect($release)->not->toBeNull();
    expect($release->is_critical)->toBeTrue();
    expect($release->published_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'release_created',
        'auditable_type' => Release::class,
    ]);
});

test('super admin can update release package details', function () {
    $release = Release::create([
        'version' => '0.2.5',
        'release_channel' => 'beta',
        'target_platform' => 'desktop_windows',
        'title' => 'Beta Preview',
        'is_published' => false,
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('superadmin.releases.update', $release->id), [
            'version' => '0.2.5',
            'release_channel' => 'stable',
            'target_platform' => 'desktop_windows',
            'title' => 'Promoted to Stable Production',
            'summary' => 'Tested across 50 beta test stores.',
            'is_published' => true,
        ])
        ->assertRedirect();

    $fresh = $release->fresh();
    expect($fresh->release_channel)->toBe('stable');
    expect($fresh->title)->toBe('Promoted to Stable Production');
    expect($fresh->is_published)->toBeTrue();
});

test('super admin can toggle publish status of a release', function () {
    $release = Release::create([
        'version' => '0.2.8',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'title' => 'Hotfix Release',
        'is_published' => true,
        'published_at' => now(),
    ]);

    // Unpublish
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.releases.publish', $release->id))
        ->assertRedirect();

    expect($release->fresh()->is_published)->toBeFalse();

    // Re-publish
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.releases.publish', $release->id))
        ->assertRedirect();

    expect($release->fresh()->is_published)->toBeTrue();
});

test('super admin can delete a release package', function () {
    $release = Release::create([
        'version' => '0.9.9-obsolete',
        'release_channel' => 'nightly',
        'target_platform' => 'desktop_windows',
        'title' => 'Experimental Branch',
        'is_published' => false,
    ]);

    $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.releases.destroy', $release->id))
        ->assertRedirect();

    expect(Release::find($release->id))->toBeNull();
});

test('tauri OTA update: returns 200 with manifest when newer version exists', function () {
    Release::create([
        'version' => '0.2.1',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'tauri_target' => 'windows-x86_64',
        'title' => 'FBR Security Update',
        'summary' => 'Mandatory update for tax calculations.',
        'changelog_markdown' => '### FBR Updates\n- Updated rate schedule',
        'download_url' => 'https://releases.pkpos.pk/downloads/pk-pos-0.2.1.msi',
        'signature' => 'SIG_MINISIGN_VALID',
        'is_published' => true,
        'published_at' => now(),
    ]);

    // Client requests update for version 0.1.9
    $response = $this->get('/api/v1/updates/desktop/windows-x86_64/0.1.9');

    $response->assertOk()
        ->assertJson([
            'version' => '0.2.1',
            'signature' => 'SIG_MINISIGN_VALID',
            'url' => 'https://releases.pkpos.pk/downloads/pk-pos-0.2.1.msi',
        ])
        ->assertJsonStructure([
            'version',
            'notes',
            'pub_date',
            'signature',
            'url',
            'platforms' => [
                'windows-x86_64' => ['signature', 'url'],
            ],
        ]);
});

test('tauri OTA update: returns 204 No Content when client is already up to date', function () {
    Release::create([
        'version' => '0.2.1',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'tauri_target' => 'windows-x86_64',
        'title' => 'Current Production',
        'is_published' => true,
        'published_at' => now(),
    ]);

    // Client is already on 0.2.1
    $response = $this->get('/api/v1/updates/desktop/windows-x86_64/0.2.1');
    $response->assertNoContent();

    // Client is on a newer version (e.g. 0.3.0-dev)
    $newerResponse = $this->get('/api/v1/updates/desktop/windows-x86_64/0.3.0');
    $newerResponse->assertNoContent();
});

test('public changelog API: returns list of published releases', function () {
    Release::create([
        'version' => '0.2.0',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'title' => 'FBR SRO Compliance',
        'summary' => 'Summary text',
        'changelog_markdown' => 'Markdown notes',
        'is_published' => true,
        'published_at' => now(),
    ]);

    Release::create([
        'version' => '0.9.0-draft',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'title' => 'Unpublished Draft',
        'is_published' => false,
    ]);

    $response = $this->getJson('/api/v1/changelog?target_platform=desktop_windows');

    $response->assertOk()
        ->assertJsonStructure(['releases'])
        ->assertJsonMissing(['title' => 'Unpublished Draft']);
});

test('version check API: correctly flags outdated and deprecated client versions', function () {
    Release::create([
        'version' => '0.2.5',
        'release_channel' => 'stable',
        'target_platform' => 'desktop_windows',
        'title' => 'Fiscal Enforcement Release',
        'min_supported_version' => '0.2.0',
        'is_critical' => true,
        'download_url' => 'https://releases.pkpos.pk/downloads/pk-pos-0.2.5.msi',
        'is_published' => true,
        'published_at' => now(),
    ]);

    // Test client on obsolete version (0.1.0 is below min 0.2.0)
    $response = $this->getJson('/api/v1/version/check?current_version=0.1.0');

    $response->assertOk()
        ->assertJson([
            'current_version' => '0.1.0',
            'latest_version' => '0.2.5',
            'is_outdated' => true,
            'is_deprecated' => true,
            'is_critical' => true,
            'min_supported_version' => '0.2.0',
        ]);

    // Test client on acceptable version (0.2.2 is >= 0.2.0, outdated but not deprecated)
    $okResponse = $this->getJson('/api/v1/version/check?current_version=0.2.2');

    $okResponse->assertOk()
        ->assertJson([
            'current_version' => '0.2.2',
            'latest_version' => '0.2.5',
            'is_outdated' => true,
            'is_deprecated' => false,
        ]);
});

