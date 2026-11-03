<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Product;
use App\Models\TaxRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Platform Roles and Granular Permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // Platform Super Administrator (no tenant)
        User::create([
            'tenant_id' => null,
            'name' => 'Super Administrator',
            'email' => 'superadmin@example.com',
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
            'role' => 'owner',
            'super_admin' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Apex Retailers Pakistan',
            'ntn' => '1234567-8',
            'strn' => '12-34-5678-901-23',
            'province' => 'punjab',
            'fbr_pos_id' => 'POS-1001',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Owner',
            'email' => 'owner@pkpos.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $location = Location::create([
            'tenant_id' => $tenant->id,
            'name' => 'Lahore Main Branch',
            'address' => 'Mall Road, Lahore',
            'province' => 'punjab',
        ]);

        // Federal Tax Rule: category "general_goods", payment_method "any", rate 1800, authority fbr, effective_from today
        TaxRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Federal Standard GST (Goods)',
            'tax_category' => 'general_goods',
            'province' => 'federal',
            'payment_method' => 'any',
            'rate_basis_points' => 1800,
            'authority' => 'fbr',
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Sample Catalog Products
        $p1 = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Basmati Rice 5kg',
            'sku' => 'RICE-BAS-5KG',
            'barcode' => '8964000100011',
            'hs_code' => '10063010',
            'unit' => 'kg',
            'price_minor' => 185000, // Rs 1,850.00
            'cost_minor' => 150000,
            'tax_category' => 'general_goods',
            'is_active' => true,
        ]);

        $p2 = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sunflower Oil 1L',
            'sku' => 'OIL-SUN-1L',
            'barcode' => '8964000100022',
            'hs_code' => '15121100',
            'unit' => 'litre',
            'price_minor' => 55000, // Rs 550.00
            'cost_minor' => 45000,
            'tax_category' => 'general_goods',
            'is_active' => true,
        ]);

        $p3 = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Tea Pack 250g',
            'sku' => 'TEA-PAK-250G',
            'barcode' => '8964000100033',
            'hs_code' => '09023000',
            'unit' => 'piece',
            'price_minor' => 42000, // Rs 420.00
            'cost_minor' => 34000,
            'tax_category' => 'general_goods',
            'is_active' => true,
        ]);

        // Seed opening stock movements (+100 each) and stock levels
        $stockService = app(StockService::class);
        foreach ([$p1, $p2, $p3] as $prod) {
            $stockService->recordMovement(
                tenant: $tenant,
                productId: $prod->id,
                locationId: $location->id,
                movementType: 'opening',
                quantityDelta: 100,
                referenceType: null,
                referenceId: null,
                note: 'Initial opening stock',
                createdBy: $user->id
            );
        }
    }
}
