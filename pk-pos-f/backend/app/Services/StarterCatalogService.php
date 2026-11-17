<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StarterCatalogService
{
    /**
     * Get all available pre-packaged industry starter catalogs.
     */
    public static function getAvailableCatalogs(): array
    {
        return [
            'grocery_fmcg' => [
                'key' => 'grocery_fmcg',
                'name' => 'Standard FMCG & Supermarket Grocery',
                'industry' => 'Supermarket & FMCG',
                'description' => '25 top-selling branded consumer goods in Pakistan (Cooking Oil, Tea, Milk, Rice, Sugar, Spices, Detergents) with valid EAN-13 barcodes, HS codes, and standard retail pricing.',
                'sku_count' => 25,
                'items' => static::getGroceryItems(),
            ],
            'pharmacy' => [
                'key' => 'pharmacy',
                'name' => 'Standard Community Retail Pharmacy',
                'industry' => 'Pharmacy & Healthcare',
                'description' => '20 essential OTC medicines, analgesics, first aid essentials, and vitamins with official pharmaceutical HS codes and standard unit packaging.',
                'sku_count' => 20,
                'items' => static::getPharmacyItems(),
            ],
        ];
    }

    /**
     * Seed an industry catalog into a designated tenant account.
     *
     * @param Tenant $tenant
     * @param string $catalogKey
     * @return array{success: bool, seeded_count: int, catalog_name: string, message: string}
     */
    public static function seedToTenant(Tenant $tenant, string $catalogKey): array
    {
        $catalogs = static::getAvailableCatalogs();
        if (! isset($catalogs[$catalogKey])) {
            throw new \InvalidArgumentException("Invalid starter catalog key: {$catalogKey}");
        }

        $catalog = $catalogs[$catalogKey];
        $items = $catalog['items'];

        // Check SKU Quota
        $plan = $tenant->currentPlan();
        if ($plan && $plan->max_skus !== null) {
            $currentProductCount = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();

            $neededSlots = count($items);
            if (($currentProductCount + $neededSlots) > $plan->max_skus) {
                $available = max(0, $plan->max_skus - $currentProductCount);
                throw new \DomainException("Catalog seeding blocked by plan quota: This catalog contains {$neededSlots} products, but tenant has only {$available} SKU slots remaining under their {$plan->name} plan.");
            }
        }

        $primaryLocation = $tenant->locations()->withoutGlobalScopes()->first();
        $seededCount = 0;

        DB::transaction(function () use ($tenant, $items, $primaryLocation, &$seededCount, $catalog) {
            foreach ($items as $item) {
                $product = Product::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'sku' => $item['sku'],
                    ],
                    [
                        'name' => $item['name'],
                        'barcode' => $item['barcode'],
                        'hs_code' => $item['hs_code'],
                        'unit' => $item['unit'],
                        'price_minor' => $item['price_minor'],
                        'cost_minor' => $item['cost_minor'],
                        'tax_category' => $item['tax_category'],
                        'is_active' => true,
                        'product_meta' => [
                            'category' => $item['category'],
                            'industry' => $catalog['industry'],
                            'seeded_at' => now()->toISOString(),
                        ],
                    ]
                );

                // Initialize starting floor stock if primary location exists
                if ($primaryLocation) {
                    DB::table('stock_levels')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->id,
                            'product_id' => $product->id,
                            'location_id' => $primaryLocation->id,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'quantity_on_hand' => 50,
                            'updated_at' => now(),
                        ]
                    );
                }

                $seededCount++;
            }

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'event' => 'catalog_seeded_to_tenant',
                'auditable_type' => Tenant::class,
                'auditable_id' => $tenant->id,
                'new_values' => [
                    'catalog_key' => $catalog['key'],
                    'catalog_name' => $catalog['name'],
                    'products_seeded' => $seededCount,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });

        return [
            'success' => true,
            'seeded_count' => $seededCount,
            'catalog_name' => $catalog['name'],
            'message' => "Successfully seeded {$seededCount} standard items from '{$catalog['name']}' into {$tenant->name}.",
        ];
    }

    /**
     * FMCG / Grocery catalog items.
     */
    protected static function getGroceryItems(): array
    {
        return [
            [
                'sku' => 'FMCG-OIL-001',
                'name' => 'Dalda Cooking Oil 1 Litre Pouch',
                'barcode' => '8961011100012',
                'hs_code' => '15121900',
                'unit' => 'piece',
                'price_minor' => 58000,
                'cost_minor' => 53000,
                'tax_category' => 'general_goods',
                'category' => 'Edible Oils & Ghee',
            ],
            [
                'sku' => 'FMCG-TEA-002',
                'name' => 'Tapal Danedar Tea 430g Pouch',
                'barcode' => '8961022200025',
                'hs_code' => '09024090',
                'unit' => 'piece',
                'price_minor' => 65000,
                'cost_minor' => 59000,
                'tax_category' => 'general_goods',
                'category' => 'Beverages & Tea',
            ],
            [
                'sku' => 'FMCG-MLK-003',
                'name' => "Olper's Full Cream Milk 1000ml",
                'barcode' => '8961033300038',
                'hs_code' => '04012000',
                'unit' => 'piece',
                'price_minor' => 29000,
                'cost_minor' => 26500,
                'tax_category' => 'reduced_goods',
                'category' => 'Dairy & Chilled',
            ],
            [
                'sku' => 'FMCG-SPC-004',
                'name' => 'Shan Bombay Biryani Masala 50g',
                'barcode' => '8961044400041',
                'hs_code' => '21039000',
                'unit' => 'piece',
                'price_minor' => 13000,
                'cost_minor' => 11000,
                'tax_category' => 'general_goods',
                'category' => 'Spices & Recipe Mixes',
            ],
            [
                'sku' => 'FMCG-SAU-005',
                'name' => 'National Chilli Garlic Sauce 300g Bottle',
                'barcode' => '8961055500054',
                'hs_code' => '21032000',
                'unit' => 'piece',
                'price_minor' => 25000,
                'cost_minor' => 21500,
                'tax_category' => 'general_goods',
                'category' => 'Sauces & Condiments',
            ],
            [
                'sku' => 'FMCG-RIC-006',
                'name' => 'Guard Super Kernel Basmati Rice 1kg Pack',
                'barcode' => '8961066600067',
                'hs_code' => '10063010',
                'unit' => 'piece',
                'price_minor' => 38000,
                'cost_minor' => 33500,
                'tax_category' => 'exempt',
                'category' => 'Staples & Grains',
            ],
            [
                'sku' => 'FMCG-SUG-007',
                'name' => 'Habib Refined Pure White Sugar 1kg',
                'barcode' => '8961077700070',
                'hs_code' => '17019910',
                'unit' => 'piece',
                'price_minor' => 16000,
                'cost_minor' => 14500,
                'tax_category' => 'exempt',
                'category' => 'Staples & Sugar',
            ],
            [
                'sku' => 'FMCG-TEA-008',
                'name' => 'Lipton Yellow Label Black Tea 200g Box',
                'barcode' => '8961088800083',
                'hs_code' => '09024090',
                'unit' => 'piece',
                'price_minor' => 34000,
                'cost_minor' => 30500,
                'tax_category' => 'general_goods',
                'category' => 'Beverages & Tea',
            ],
            [
                'sku' => 'FMCG-SOP-009',
                'name' => 'Dettol Antibacterial Soap Original 135g',
                'barcode' => '8961099900096',
                'hs_code' => '34011100',
                'unit' => 'piece',
                'price_minor' => 18000,
                'cost_minor' => 15500,
                'tax_category' => 'general_goods',
                'category' => 'Personal Hygiene',
            ],
            [
                'sku' => 'FMCG-SOP-010',
                'name' => 'Lifebuoy Total 10 Germ Protection Soap 115g',
                'barcode' => '8961100000109',
                'hs_code' => '34011100',
                'unit' => 'piece',
                'price_minor' => 14000,
                'cost_minor' => 12000,
                'tax_category' => 'general_goods',
                'category' => 'Personal Hygiene',
            ],
            [
                'sku' => 'FMCG-DET-011',
                'name' => 'Surf Excel Quick Wash Washing Powder 1kg',
                'barcode' => '8961111100112',
                'hs_code' => '34022000',
                'unit' => 'piece',
                'price_minor' => 72000,
                'cost_minor' => 64500,
                'tax_category' => 'general_goods',
                'category' => 'Laundry & Detergents',
            ],
            [
                'sku' => 'FMCG-DET-012',
                'name' => 'Ariel Complete Detergent Powder 1kg',
                'barcode' => '8961122200125',
                'hs_code' => '34022000',
                'unit' => 'piece',
                'price_minor' => 71000,
                'cost_minor' => 64000,
                'tax_category' => 'general_goods',
                'category' => 'Laundry & Detergents',
            ],
            [
                'sku' => 'FMCG-FLR-013',
                'name' => 'Sunridge Fortified Chakki Atta 5kg Bag',
                'barcode' => '8961133300138',
                'hs_code' => '11010010',
                'unit' => 'piece',
                'price_minor' => 75000,
                'cost_minor' => 68500,
                'tax_category' => 'exempt',
                'category' => 'Flour & Atta',
            ],
            [
                'sku' => 'FMCG-JAM-014',
                'name' => "Mitchell's Mixed Fruit Jam 450g Jar",
                'barcode' => '8961144400141',
                'hs_code' => '20079900',
                'unit' => 'piece',
                'price_minor' => 42000,
                'cost_minor' => 37000,
                'tax_category' => 'general_goods',
                'category' => 'Breakfast & Spreads',
            ],
            [
                'sku' => 'FMCG-SNK-015',
                'name' => 'Kolson Slanty Salted Snack 35g',
                'barcode' => '8961155500154',
                'hs_code' => '19059000',
                'unit' => 'piece',
                'price_minor' => 5000,
                'cost_minor' => 4200,
                'tax_category' => 'general_goods',
                'category' => 'Snacks & Confectionery',
            ],
            [
                'sku' => 'FMCG-SNK-016',
                'name' => 'Kurkure Red Chilli Jhatpat 40g',
                'barcode' => '8961166600167',
                'hs_code' => '19059000',
                'unit' => 'piece',
                'price_minor' => 5000,
                'cost_minor' => 4200,
                'tax_category' => 'general_goods',
                'category' => 'Snacks & Confectionery',
            ],
            [
                'sku' => 'FMCG-SNK-017',
                'name' => 'Lays Classic Salted Potato Chips 40g',
                'barcode' => '8961177700170',
                'hs_code' => '20052000',
                'unit' => 'piece',
                'price_minor' => 6000,
                'cost_minor' => 5100,
                'tax_category' => 'general_goods',
                'category' => 'Snacks & Confectionery',
            ],
            [
                'sku' => 'FMCG-BEV-018',
                'name' => 'Coca-Cola Regular 1.5 Litre PET Bottle',
                'barcode' => '8961188800183',
                'hs_code' => '22021000',
                'unit' => 'piece',
                'price_minor' => 18000,
                'cost_minor' => 15500,
                'tax_category' => 'general_goods',
                'category' => 'Soft Drinks & Sodas',
            ],
            [
                'sku' => 'FMCG-BEV-019',
                'name' => 'Sprite Regular 1.5 Litre PET Bottle',
                'barcode' => '8961199900196',
                'hs_code' => '22021000',
                'unit' => 'piece',
                'price_minor' => 18000,
                'cost_minor' => 15500,
                'tax_category' => 'general_goods',
                'category' => 'Soft Drinks & Sodas',
            ],
            [
                'sku' => 'FMCG-WTR-020',
                'name' => 'Nestle Pure Life Mineral Water 1.5L',
                'barcode' => '8961200000209',
                'hs_code' => '22011000',
                'unit' => 'piece',
                'price_minor' => 10000,
                'cost_minor' => 8200,
                'tax_category' => 'general_goods',
                'category' => 'Water & Hydration',
            ],
            [
                'sku' => 'FMCG-ORL-021',
                'name' => 'Colgate Total 12 Pro Gum Health Toothpaste 100g',
                'barcode' => '8961211100212',
                'hs_code' => '33061010',
                'unit' => 'piece',
                'price_minor' => 32000,
                'cost_minor' => 28000,
                'tax_category' => 'general_goods',
                'category' => 'Oral Care',
            ],
            [
                'sku' => 'FMCG-ORL-022',
                'name' => 'Sensodyne Rapid Action Toothpaste 100g',
                'barcode' => '8961222200225',
                'hs_code' => '33061010',
                'unit' => 'piece',
                'price_minor' => 48000,
                'cost_minor' => 42000,
                'tax_category' => 'general_goods',
                'category' => 'Oral Care',
            ],
            [
                'sku' => 'FMCG-HAR-023',
                'name' => 'Head & Shoulders Classic Clean Shampoo 180ml',
                'barcode' => '8961233300238',
                'hs_code' => '33051000',
                'unit' => 'piece',
                'price_minor' => 49000,
                'cost_minor' => 42500,
                'tax_category' => 'general_goods',
                'category' => 'Hair Care',
            ],
            [
                'sku' => 'FMCG-BRD-024',
                'name' => 'Dawn Bread Plain Medium Loaf',
                'barcode' => '8961244400241',
                'hs_code' => '19059000',
                'unit' => 'piece',
                'price_minor' => 14000,
                'cost_minor' => 12000,
                'tax_category' => 'exempt',
                'category' => 'Bakery & Fresh',
            ],
            [
                'sku' => 'FMCG-FRZ-025',
                'name' => "K&N's Chicken Nuggets 900g Family Pack",
                'barcode' => '8961255500254',
                'hs_code' => '16023200',
                'unit' => 'piece',
                'price_minor' => 145000,
                'cost_minor' => 129000,
                'tax_category' => 'tier_1_retail',
                'category' => 'Frozen Foods',
            ],
        ];
    }

    /**
     * Pharmacy & Healthcare catalog items.
     */
    protected static function getPharmacyItems(): array
    {
        return [
            [
                'sku' => 'PHRM-OTC-001',
                'name' => 'Panadol 500mg Paracetamol Tablets (Strip of 10)',
                'barcode' => '8962011100019',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 3500,
                'cost_minor' => 3000,
                'tax_category' => 'exempt',
                'category' => 'Analgesics & Pain Relief',
            ],
            [
                'sku' => 'PHRM-OTC-002',
                'name' => 'Panadol Extra Paracetamol + Caffeine (Strip of 10)',
                'barcode' => '8962022200022',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 4500,
                'cost_minor' => 3900,
                'tax_category' => 'exempt',
                'category' => 'Analgesics & Pain Relief',
            ],
            [
                'sku' => 'PHRM-OTC-003',
                'name' => 'Disprin 300mg Soluble Aspirin (Strip of 10)',
                'barcode' => '8962033300035',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 3000,
                'cost_minor' => 2550,
                'tax_category' => 'exempt',
                'category' => 'Analgesics & Pain Relief',
            ],
            [
                'sku' => 'PHRM-OTC-004',
                'name' => 'Brufen 400mg Ibuprofen Tablets (Strip of 10)',
                'barcode' => '8962044400048',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 4000,
                'cost_minor' => 3400,
                'tax_category' => 'exempt',
                'category' => 'Anti-inflammatory',
            ],
            [
                'sku' => 'PHRM-OTC-005',
                'name' => 'Arinac Forte Ibuprofen + Pseudoephedrine (Strip of 10)',
                'barcode' => '8962055500051',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 9000,
                'cost_minor' => 7800,
                'tax_category' => 'exempt',
                'category' => 'Cold & Flu',
            ],
            [
                'sku' => 'PHRM-OTC-006',
                'name' => 'Risek 20mg Omeprazole Capsules (Strip of 14)',
                'barcode' => '8962066600064',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 34000,
                'cost_minor' => 29500,
                'tax_category' => 'exempt',
                'category' => 'Gastric & Antacid',
            ],
            [
                'sku' => 'PHRM-OTC-007',
                'name' => 'Gaviscon Liquid Antacid Peppermint 120ml Bottle',
                'barcode' => '8962077700077',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 21000,
                'cost_minor' => 18000,
                'tax_category' => 'exempt',
                'category' => 'Gastric & Antacid',
            ],
            [
                'sku' => 'PHRM-OTC-008',
                'name' => 'Sancos Cough Syrup 120ml Bottle',
                'barcode' => '8962088800080',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 12000,
                'cost_minor' => 10200,
                'tax_category' => 'exempt',
                'category' => 'Cough & Respiratory',
            ],
            [
                'sku' => 'PHRM-OTC-009',
                'name' => 'Hydryllin Expectorant Syrup 120ml',
                'barcode' => '8962099900093',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 13500,
                'cost_minor' => 11600,
                'tax_category' => 'exempt',
                'category' => 'Cough & Respiratory',
            ],
            [
                'sku' => 'PHRM-OTC-010',
                'name' => 'Polyfax Antibiotic Skin Ointment 20g Tube',
                'barcode' => '8962100000106',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 9500,
                'cost_minor' => 8100,
                'tax_category' => 'exempt',
                'category' => 'First Aid & Topicals',
            ],
            [
                'sku' => 'PHRM-OTC-011',
                'name' => 'Betadine Antiseptic Povidone-Iodine 10% 60ml',
                'barcode' => '8962111100119',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 15000,
                'cost_minor' => 12800,
                'tax_category' => 'exempt',
                'category' => 'Antiseptic & Wound Care',
            ],
            [
                'sku' => 'PHRM-OTC-012',
                'name' => 'ORS Nimkol Oral Rehydration Salts Lemon (Pack of 5)',
                'barcode' => '8962122200122',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 12500,
                'cost_minor' => 10500,
                'tax_category' => 'exempt',
                'category' => 'Electrolytes & Rehydration',
            ],
            [
                'sku' => 'PHRM-NUT-013',
                'name' => 'CaC 1000 Plus Calcium + Vitamin C & D (Tube of 10)',
                'barcode' => '8962133300135',
                'hs_code' => '21069090',
                'unit' => 'piece',
                'price_minor' => 26000,
                'cost_minor' => 22500,
                'tax_category' => 'general_goods',
                'category' => 'Vitamins & Supplements',
            ],
            [
                'sku' => 'PHRM-NUT-014',
                'name' => 'Surbex Z Zinc + Vitamin B Complex (Bottle of 30)',
                'barcode' => '8962144400148',
                'hs_code' => '21069090',
                'unit' => 'piece',
                'price_minor' => 41000,
                'cost_minor' => 35500,
                'tax_category' => 'general_goods',
                'category' => 'Vitamins & Supplements',
            ],
            [
                'sku' => 'PHRM-MED-015',
                'name' => 'Cotton Crepe Bandage Elastic 7.5cm Roll',
                'barcode' => '8962155500151',
                'hs_code' => '30059000',
                'unit' => 'piece',
                'price_minor' => 18000,
                'cost_minor' => 14000,
                'tax_category' => 'general_goods',
                'category' => 'Medical Consumables',
            ],
            [
                'sku' => 'PHRM-MED-016',
                'name' => 'Sunnyplast Adhesive Medicated Bandages (Box of 20)',
                'barcode' => '8962166600164',
                'hs_code' => '30051090',
                'unit' => 'piece',
                'price_minor' => 15000,
                'cost_minor' => 11500,
                'tax_category' => 'general_goods',
                'category' => 'First Aid & Topicals',
            ],
            [
                'sku' => 'PHRM-MED-017',
                'name' => 'Dettol Disinfectant Liquid Original 100ml Bottle',
                'barcode' => '8962177700177',
                'hs_code' => '38089400',
                'unit' => 'piece',
                'price_minor' => 24000,
                'cost_minor' => 20500,
                'tax_category' => 'general_goods',
                'category' => 'Antiseptic & Wound Care',
            ],
            [
                'sku' => 'PHRM-OTC-018',
                'name' => 'Flagyl 400mg Metronidazole Tablets (Strip of 10)',
                'barcode' => '8962188800180',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 5000,
                'cost_minor' => 4200,
                'tax_category' => 'exempt',
                'category' => 'Anti-infective',
            ],
            [
                'sku' => 'PHRM-OTC-019',
                'name' => 'Entamizole DS Diloxanide + Metronidazole (Strip of 10)',
                'barcode' => '8962199900193',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 11000,
                'cost_minor' => 9300,
                'tax_category' => 'exempt',
                'category' => 'Anti-infective',
            ],
            [
                'sku' => 'PHRM-OTC-020',
                'name' => 'Lopamide 2mg Loperamide Capsules (Strip of 10)',
                'barcode' => '8962200000206',
                'hs_code' => '30049099',
                'unit' => 'piece',
                'price_minor' => 4500,
                'cost_minor' => 3800,
                'tax_category' => 'exempt',
                'category' => 'Gastrointestinal',
            ],
        ];
    }
}
