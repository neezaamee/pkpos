<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'sale_number' => 'INV-' . strtoupper(Str::random(8)),
            'payment_method' => fake()->randomElement(['cash', 'card', 'raast', 'wallet']),
            'currency' => 'PKR',
            'subtotal_minor' => 100000,
            'tax_minor' => 18000,
            'total_minor' => 118000,
            'status' => 'completed',
            'fbr_usin' => null,
            'fbr_status' => 'pending',
            'fbr_error' => null,
            'fbr_submitted_at' => null,
        ];
    }
}
