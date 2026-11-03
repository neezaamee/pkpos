<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\FbrCredential;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionTenantAction
{
    /**
     * 1-Click Provisioning Engine: Materializes Tenant, Owner User, Default Location,
     * Sandbox FbrCredentials, and Subscription from an approved TenantApplication.
     *
     * @param TenantApplication $application
     * @param string $tempPassword
     * @param string|null $planId
     * @param string|null $staffUserId
     * @param string|null $internalNotes
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return Tenant
     */
    public function execute(
        TenantApplication $application,
        string $tempPassword,
        ?string $planId = null,
        ?string $staffUserId = null,
        ?string $internalNotes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Tenant {
        return DB::transaction(function () use (
            $application,
            $tempPassword,
            $planId,
            $staffUserId,
            $internalNotes,
            $ipAddress,
            $userAgent
        ) {
            $posId = 'POS-' . strtoupper(Str::random(6));

            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $application->business_name,
                'ntn' => $application->ntn,
                'strn' => $application->strn,
                'province' => $application->province,
                'fbr_pos_id' => $posId,
                'notes' => "Provisioned from self-registration #{$application->application_number}",
            ]);

            // 2. Create Primary Store Owner User
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $application->contact_name,
                'email' => $application->contact_email,
                'password' => Hash::make($tempPassword),
                'role' => 'owner',
                'super_admin' => false,
                'is_active' => true,
            ]);

            // 3. Create Default Location (Head Branch)
            Location::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Head Branch',
                'address' => $application->address ?: ($application->city . ', ' . ucfirst($application->province)),
                'province' => $application->province,
            ]);

            // 4. Create Default FbrCredential Sandbox Record
            FbrCredential::create([
                'tenant_id' => $tenant->id,
                'pos_id' => $posId,
                'ntn' => $application->ntn ?: '1000000-0',
                'strn' => $application->strn ?: '00-00-0000-000-00',
                'bearer_token' => 'SANDBOX_' . Str::random(32),
            ]);

            // 5. Assign Subscription Tier
            $targetPlanId = $planId ?? $application->requested_plan_id ?? Plan::first()?->id;
            if ($targetPlanId) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $targetPlanId,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(),
                ]);
            }

            // 6. Update Application Adjudication State
            $application->update([
                'status' => 'approved',
                'provisioned_tenant_id' => $tenant->id,
                'reviewed_by' => $staffUserId,
                'reviewed_at' => now(),
                'internal_notes' => $internalNotes,
            ]);

            // 7. Record Immutable Platform Audit Log
            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => $staffUserId,
                'event' => 'merchant_application_approved_and_provisioned',
                'auditable_type' => TenantApplication::class,
                'auditable_id' => $application->id,
                'new_values' => [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'owner_email' => $user->email,
                    'application_number' => $application->application_number,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $tenant;
        });
    }
}

