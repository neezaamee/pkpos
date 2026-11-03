<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_name' => fake()->words(2, true),
            'hs_code' => '1001.99.00',
            'quantity' => 1,
            'unit_price_minor' => 100000,
            'tax_rate_basis_points' => 1800,
            'tax_amount_minor' => 18000,
            'line_total_minor' => 118000,
        ];
    }
}
