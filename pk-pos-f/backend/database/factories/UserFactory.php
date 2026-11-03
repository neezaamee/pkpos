<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['owner', 'manager', 'cashier']),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Set role as owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
        ]);
    }

    /**
     * Set user as platform super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => 'owner',
            'super_admin' => true,
            'staff_role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    /**
     * Set user as platform support agent.
     */
    public function supportAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => 'owner',
            'super_admin' => true,
            'staff_role' => 'support_agent',
            'is_active' => true,
        ]);
    }

    /**
     * Set user as platform compliance auditor.
     */
    public function complianceAuditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => 'owner',
            'super_admin' => true,
            'staff_role' => 'compliance_auditor',
            'is_active' => true,
        ]);
    }
}
