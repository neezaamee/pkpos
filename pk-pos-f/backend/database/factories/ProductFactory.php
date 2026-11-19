<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-????')),
            'barcode' => fake()->unique()->numerify('8964000#####'),
            'hs_code' => '21039000',
            'unit' => 'pcs',
            'price_minor' => 10000,
            'cost_minor' => 7000,
            'tax_category' => 'general_goods',
            'is_active' => true,
        ];
    }
}
