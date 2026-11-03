<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'ntn' => fake()->numerify('#######-#'),
            'strn' => fake()->numerify('##-##-####-###-##'),
            'province' => fake()->randomElement(['punjab', 'sindh', 'kp', 'balochistan']),
            'fbr_pos_id' => 'POS-' . fake()->numerify('####'),
        ];
    }
}
