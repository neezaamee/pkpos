<?php

use App\Models\AuditLog;
use App\Models\MasterHsCode;
use App\Models\ProvincialTaxTemplate;
use App\Models\TaxRule;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->punjabTenant = Tenant::factory()->create([
        'name' => 'Lahore Retail Mart',
        'province' => 'punjab',
    ]);

    $this->sindhTenant = Tenant::factory()->create([
        'name' => 'Karachi Cash & Carry',
        'province' => 'sindh',
    ]);

    $this->regularUser = User::factory()->create([
        'tenant_id' => $this->punjabTenant->id,
        'role' => 'cashier',
        'super_admin' => false,
    ]);
});

test('non-super admin cannot access master data endpoints', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.master-data.hs-codes.index'))
        ->assertForbidden();

    $this->actingAs($this->regularUser)
        ->get(route('superadmin.master-data.tax-templates.index'))
        ->assertForbidden();
});

test('super admin can view master hs codes catalog', function () {
    MasterHsCode::create([
        'hs_code' => '1006.3010',
        'description' => 'Basmati Rice',
        'tax_category' => 'general_goods',
        'default_rate_basis_points' => 1800,
        'authority' => 'fbr',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.master-data.hs-codes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/MasterData/HsCodes')
            ->has('hsCodes.data', 1)
            ->where('hsCodes.data.0.hs_code', '1006.3010')
            ->where('hsCodes.data.0.default_rate_pct', 18)
        );
});

test('super admin can create a new master hs code', function () {
    $payload = [
        'hs_code' => '8517.1300',
        'description' => 'Smartphones and cellular devices',
        'tax_category' => 'tier_1_retail',
        'default_rate_basis_points' => 1800,
        'authority' => 'fbr',
        'is_active' => true,
    ];

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.master-data.hs-codes.store'), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('master_hs_codes', [
        'hs_code' => '8517.1300',
        'tax_category' => 'tier_1_retail',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => MasterHsCode::class,
    ]);
});

test('super admin can update an existing master hs code', function () {
    $code = MasterHsCode::create([
        'hs_code' => '3004.9099',
        'description' => 'Medicaments',
        'tax_category' => 'reduced_goods',
        'default_rate_basis_points' => 100,
        'authority' => 'fbr',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('superadmin.master-data.hs-codes.update', $code->id), [
            'hs_code' => '3004.9099',
            'description' => 'Essential therapeutic medicaments and drugs',
            'tax_category' => 'reduced_goods',
            'default_rate_basis_points' => 200,
            'authority' => 'fbr',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($code->fresh()->description)->toBe('Essential therapeutic medicaments and drugs')
        ->and($code->fresh()->default_rate_basis_points)->toBe(200);
});

test('super admin can bulk import hs codes via csv data', function () {
    $csvData = "hs_code,description,tax_category,default_rate_basis_points,authority\n" .
        "0902.3000,Black tea in packings <= 3kg,general_goods,1800,fbr\n" .
        "9801.1000,Restaurant dining services,services,1600,pra";

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.master-data.hs-codes.import'), [
            'csv_data' => $csvData,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('master_hs_codes', ['hs_code' => '0902.3000']);
    $this->assertDatabaseHas('master_hs_codes', ['hs_code' => '9801.1000', 'authority' => 'pra']);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'hs_codes_csv_imported',
        'auditable_type' => MasterHsCode::class,
    ]);
});

test('cli command master-hs-codes:bulk-import streams and upserts tariff codes with audit log', function () {
    $tempCsv = tempnam(sys_get_temp_dir(), 'hs_test_') . '.csv';
    $content = "pct_code,item_description,tax_category,rate_bps,authority\n" .
        "1006.3010,Basmati Rice Parboiled or Polished,general_goods,1800,fbr\n" .
        "8517.1300,Smartphones 5G Cellular,tier_1_retail,1800,fbr\n";
    file_put_contents($tempCsv, $content);

    $this->artisan('master-hs-codes:bulk-import', [
        'file' => $tempCsv,
        '--chunk' => 10,
    ])
        ->assertSuccessful();

    $this->assertDatabaseHas('master_hs_codes', [
        'hs_code' => '1006.3010',
        'description' => 'Basmati Rice Parboiled or Polished',
    ]);
    $this->assertDatabaseHas('master_hs_codes', [
        'hs_code' => '8517.1300',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'hs_codes_cli_bulk_imported',
    ]);

    @unlink($tempCsv);
});

test('super admin can view provincial tax templates', function () {
    ProvincialTaxTemplate::create([
        'province' => 'punjab',
        'name' => 'Punjab Standard Services Tax',
        'authority' => 'pra',
        'tax_category' => 'services',
        'payment_method' => 'any',
        'rate_basis_points' => 1600,
        'effective_from' => '2024-07-01',
        'description' => 'PRA general services rate',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.master-data.tax-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/MasterData/TaxTemplates')
            ->has('templates.data', 1)
            ->where('templates.data.0.name', 'Punjab Standard Services Tax')
            ->where('templates.data.0.rate_pct', 16)
        );
});

test('super admin can create and update provincial tax templates', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.master-data.tax-templates.store'), [
            'province' => 'sindh',
            'name' => 'Sindh Digital Dining Incentive',
            'authority' => 'srb',
            'tax_category' => 'services',
            'payment_method' => 'card',
            'rate_basis_points' => 800,
            'effective_from' => '2024-07-01',
            'is_active' => true,
            'description' => '8% reduced rate for digital payments',
        ])
        ->assertRedirect();

    $tmpl = ProvincialTaxTemplate::where('name', 'Sindh Digital Dining Incentive')->first();
    expect($tmpl)->not->toBeNull();
    expect($tmpl->rate_basis_points)->toBe(800);

    // Update template
    $this->actingAs($this->superAdmin)
        ->put(route('superadmin.master-data.tax-templates.update', $tmpl->id), [
            'province' => 'sindh',
            'name' => 'Sindh Digital Dining Incentive (Revised)',
            'authority' => 'srb',
            'tax_category' => 'services',
            'payment_method' => 'card',
            'rate_basis_points' => 700,
            'effective_from' => '2024-07-01',
            'is_active' => true,
            'description' => 'Revised to 7%',
        ])
        ->assertRedirect();

    expect($tmpl->fresh()->rate_basis_points)->toBe(700);
});

test('super admin can synchronize tax template directly into tenant tax rules', function () {
    $tmpl = ProvincialTaxTemplate::create([
        'province' => 'punjab',
        'name' => 'Punjab Digital Restaurant Incentive',
        'authority' => 'pra',
        'tax_category' => 'services',
        'payment_method' => 'card',
        'rate_basis_points' => 500,
        'effective_from' => '2024-07-01',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.master-data.tax-templates.sync', $tmpl->id))
        ->assertRedirect();

    // Check that Punjab tenant received the tax rule
    $rule = TaxRule::withoutGlobalScopes()
        ->where('tenant_id', $this->punjabTenant->id)
        ->where('tax_category', 'services')
        ->where('payment_method', 'card')
        ->first();

    expect($rule)->not->toBeNull();
    expect($rule->rate_basis_points)->toBe(500);
    expect($rule->authority)->toBe('pra');

    // Sindh tenant should NOT have received it because province is punjab
    $sindhRule = TaxRule::withoutGlobalScopes()
        ->where('tenant_id', $this->sindhTenant->id)
        ->where('tax_category', 'services')
        ->where('payment_method', 'card')
        ->first();

    expect($sindhRule)->toBeNull();

    // Verify audit log
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'tax_template_synced_to_tenants',
        'auditable_type' => ProvincialTaxTemplate::class,
    ]);
});

test('public api provides active hs codes auto-completion for pos terminals', function () {
    MasterHsCode::create([
        'hs_code' => '1006.3010',
        'description' => 'Super Kernel Basmati Rice',
        'tax_category' => 'general_goods',
        'default_rate_basis_points' => 1800,
        'authority' => 'fbr',
        'is_active' => true,
    ]);

    MasterHsCode::create([
        'hs_code' => '9999.9999',
        'description' => 'Deprecated item',
        'tax_category' => 'general_goods',
        'default_rate_basis_points' => 1800,
        'authority' => 'fbr',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/v1/master-data/hs-codes?query=Basmati');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment([
            'hs_code' => '1006.3010',
            'tax_category' => 'general_goods',
        ]);
});

