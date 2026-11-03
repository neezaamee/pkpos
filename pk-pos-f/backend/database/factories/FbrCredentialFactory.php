<?php

namespace Database\Factories;

use App\Models\FbrCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FbrCredential>
 */
class FbrCredentialFactory extends Factory
{
    protected $model = FbrCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'pos_id' => 'POS-' . fake()->numerify('####'),
            'ntn' => fake()->numerify('#######-#'),
            'strn' => fake()->numerify('##-##-####-###-##'),
            'bearer_token' => 'fbr_token_' . fake()->sha256(),
            'token_expires_at' => now()->addDays(30),
        ];
    }
}
