<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterTenantAction
{
    /**
     * Create a tenant and an owner user in a single database transaction.
     *
     * @param array<string, mixed> $data
     * @return array{tenant: Tenant, user: User, token: string}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'ntn' => $data['ntn'],
                'strn' => $data['strn'],
                'province' => $data['province'],
                'fbr_pos_id' => $data['fbr_pos_id'] ?? null,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['user_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'tenant' => $tenant,
                'user' => $user,
                'token' => $token,
            ];
        });
    }
}
