<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Definition of all granular platform permissions by module
        $permissionDefinitions = [
            'tenants' => [
                ['name' => 'View Tenants & Branches', 'slug' => 'tenants.view', 'description' => 'Inspect merchant directory and branch locations.'],
                ['name' => 'Create Merchant Tenants', 'slug' => 'tenants.create', 'description' => 'Onboard new retail tenant organizations.'],
                ['name' => 'Edit Tenant Profile', 'slug' => 'tenants.edit', 'description' => 'Modify tenant business details, NTN/STRN, and notes.'],
                ['name' => 'Suspend / Reactivate Tenants', 'slug' => 'tenants.suspend', 'description' => 'Enforce administrative account freezes and suspensions.'],
                ['name' => 'Manage Branch Locations', 'slug' => 'tenants.locations', 'description' => 'Add and configure retail store branch locations.'],
                ['name' => 'Manage Merchant Staff & Passwords', 'slug' => 'tenants.staff', 'description' => 'Create, remove, and reset credentials for merchant cashiers.'],
            ],
            'fbr' => [
                ['name' => 'View FBR Fiscal Dashboard', 'slug' => 'fbr.view', 'description' => 'View fiscalization rates, DLQ, and provisional receipts.'],
                ['name' => 'Retry Dead-Letter Invoices', 'slug' => 'fbr.retry_single', 'description' => 'Dispatch failed sales back into the FBR IRIS pipeline.'],
                ['name' => 'Bulk Retry Tenant Invoices', 'slug' => 'fbr.bulk_retry', 'description' => 'Perform bulk reprocessing on all failed sales for a tenant.'],
                ['name' => 'Test FBR Live Connectivity', 'slug' => 'fbr.ping', 'description' => 'Execute live heartbeat pings against FBR Iris endpoints.'],
            ],
            'master_data' => [
                ['name' => 'View HS Codes & Tariffs', 'slug' => 'master_data.view', 'description' => 'Browse standard Pakistan Customs HS Codes directory.'],
                ['name' => 'Manage HS Codes & CSV Import', 'slug' => 'master_data.manage_hs_codes', 'description' => 'Import tariff CSVs, add and edit master HS code records.'],
                ['name' => 'Manage & Sync Tax Templates', 'slug' => 'master_data.manage_tax_templates', 'description' => 'Configure provincial tax rules and sync to active merchants.'],
            ],
            'terminals' => [
                ['name' => 'View POS Terminal Fleet', 'slug' => 'terminals.view', 'description' => 'Monitor registered desktop cash registers across Pakistan.'],
                ['name' => 'Generate Pairing Codes', 'slug' => 'terminals.pair', 'description' => 'Generate 6-digit one-time authorization pairing codes.'],
                ['name' => 'Revoke & Killswitch Terminals', 'slug' => 'terminals.revoke', 'description' => 'Remotely revoke hardware tokens and deactivate terminals.'],
            ],
            'releases' => [
                ['name' => 'View Releases & Changelogs', 'slug' => 'releases.view', 'description' => 'View platform changelog versions and update history.'],
                ['name' => 'Publish & Manage Releases', 'slug' => 'releases.manage', 'description' => 'Draft releases, upload binaries, and publish Tauri OTA updates.'],
            ],
            'plans' => [
                ['name' => 'View SaaS Plans & Quotas', 'slug' => 'plans.view', 'description' => 'View subscription plans and pricing structures.'],
                ['name' => 'Manage Plans & Subscriptions', 'slug' => 'plans.manage', 'description' => 'Create pricing tiers, assign subscriptions, and enforce quotas.'],
            ],
            'support' => [
                ['name' => 'View Support Tickets', 'slug' => 'support.view', 'description' => 'Inspect merchant helpdesk queue and diagnostic snapshots.'],
                ['name' => 'Reply & Add Staff Notes', 'slug' => 'support.reply', 'description' => 'Send merchant responses and collaborate with private staff notes.'],
                ['name' => 'Update Ticket Status & Assign', 'slug' => 'support.status', 'description' => 'Transition ticket status and assign tickets to team members.'],
                ['name' => 'Audited Merchant Impersonation', 'slug' => 'support.impersonate', 'description' => 'Directly log into merchant stores to troubleshoot issues.'],
                ['name' => 'Manage Knowledge Base', 'slug' => 'support.knowledge_base', 'description' => 'Access and maintain support guides and playbooks.'],
            ],
            'system' => [
                ['name' => 'View Infrastructure Telemetry', 'slug' => 'system.view_health', 'description' => 'Inspect PostgreSQL latency, database size, and table row counts.'],
                ['name' => 'Monitor Failed Queue Jobs', 'slug' => 'system.view_jobs', 'description' => 'Inspect background workers and failed jobs with stack traces.'],
                ['name' => 'Retry & Purge Background Jobs', 'slug' => 'system.manage_jobs', 'description' => 'Trigger job retries, delete failed records, and flush queue.'],
            ],
            'staff' => [
                ['name' => 'View Staff Directory', 'slug' => 'staff.view', 'description' => 'View all platform staff accounts and their operational roles.'],
                ['name' => 'Manage Staff Accounts', 'slug' => 'staff.manage', 'description' => 'Create, edit, reset passwords, and deactivate staff members.'],
            ],
            'roles' => [
                ['name' => 'View Roles & Permissions', 'slug' => 'roles.view', 'description' => 'Inspect defined roles and the platform permission matrix.'],
                ['name' => 'Manage Custom Roles', 'slug' => 'roles.manage', 'description' => 'Create, modify, and delete custom roles and assign permissions.'],
            ],
            'broadcasts' => [
                ['name' => 'View Broadcasts & Alerts', 'slug' => 'broadcasts.view', 'description' => 'Inspect platform emergency banners and merchant notices.'],
                ['name' => 'Publish & Manage Broadcasts', 'slug' => 'broadcasts.manage', 'description' => 'Publish and revoke platform-wide emergency banners and notices.'],
            ],
            'billing' => [
                ['name' => 'View SaaS Invoices & Revenue', 'slug' => 'billing.view', 'description' => 'Inspect merchant subscription billing, 1Bill, and transactions.'],
                ['name' => 'Manage Invoicing & Bank Wires', 'slug' => 'billing.manage', 'description' => 'Review bank wire proofs, approve payments, and issue invoices.'],
            ],
            'onboarding' => [
                ['name' => 'View Merchant Applications', 'slug' => 'onboarding.view', 'description' => 'Inspect incoming self-registration onboarding queue.'],
                ['name' => 'Review & Approve Merchants', 'slug' => 'onboarding.manage', 'description' => 'Approve/reject self-registered merchant tenants and verify ATL.'],
            ],
            'backups' => [
                ['name' => 'View Backup Archives', 'slug' => 'backups.view', 'description' => 'Inspect statutory backup logs and fiscal retention archives.'],
                ['name' => 'Trigger Backups & Export Data', 'slug' => 'backups.manage', 'description' => 'Run database snapshots and export 6-year fiscal retention archives.'],
            ],
            'security' => [
                ['name' => 'View Security & Sessions', 'slug' => 'security.view', 'description' => 'Monitor active staff sessions and 2FA adoption telemetry.'],
                ['name' => 'Manage Sessions & Security Killswitch', 'slug' => 'security.manage', 'description' => 'Remotely terminate active sessions and enforce platform killswitches.'],
            ],
            'audit' => [
                ['name' => 'Inspect Audit Trail', 'slug' => 'audit.view', 'description' => 'Search and filter platform-wide immutable audit trail logs.'],
            ],
        ];

        $allPermissionModels = [];
        foreach ($permissionDefinitions as $module => $perms) {
            foreach ($perms as $p) {
                $permission = Permission::firstOrCreate(
                    ['slug' => $p['slug']],
                    [
                        'module' => $module,
                        'name' => $p['name'],
                        'description' => $p['description'],
                    ]
                );
                $allPermissionModels[$p['slug']] = $permission;
            }
        }

        // 2. Pre-seed Standard Enterprise Roles
        $rolesToSeed = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full unrestricted platform access across all modules, infrastructure, and billing.',
                'color' => '#ec4899', // Pink
                'is_system' => true,
                'permissions' => array_keys($allPermissionModels), // All permissions
            ],
            [
                'name' => 'Support Agent',
                'slug' => 'support_agent',
                'description' => 'Merchant helpdesk triage, ticket replies, password resets, terminal inspection, and audited impersonation.',
                'color' => '#60a5fa', // Blue
                'is_system' => true,
                'permissions' => [
                    'tenants.view',
                    'tenants.edit',
                    'tenants.staff',
                    'terminals.view',
                    'releases.view',
                    'support.view',
                    'support.reply',
                    'support.status',
                    'support.impersonate',
                    'support.knowledge_base',
                ],
            ],
            [
                'name' => 'Compliance Auditor',
                'slug' => 'compliance_auditor',
                'description' => 'Read-only fiscal regulatory scrutiny: FBR Dead-Letter Queue, provisional receipts, master HS codes, and audit logs.',
                'color' => '#fbbf24', // Amber
                'is_system' => true,
                'permissions' => [
                    'tenants.view',
                    'fbr.view',
                    'master_data.view',
                    'audit.view',
                ],
            ],
            [
                'name' => 'Billing & Accounts Specialist',
                'slug' => 'billing_specialist',
                'description' => 'Management of merchant SaaS subscription tiers, plan upgrades, and quota extensions.',
                'color' => '#34d399', // Emerald
                'is_system' => false,
                'permissions' => [
                    'tenants.view',
                    'plans.view',
                    'plans.manage',
                    'billing.view',
                    'billing.manage',
                ],
            ],
            [
                'name' => 'DevOps & Release Engineer',
                'slug' => 'release_engineer',
                'description' => 'Desktop client OTA release distribution, changelog publishing, and background queue telemetry.',
                'color' => '#a78bfa', // Purple
                'is_system' => false,
                'permissions' => [
                    'releases.view',
                    'releases.manage',
                    'broadcasts.view',
                    'broadcasts.manage',
                    'system.view_health',
                    'system.view_jobs',
                    'system.manage_jobs',
                    'backups.view',
                    'backups.manage',
                    'security.view',
                ],
            ],
        ];

        foreach ($rolesToSeed as $r) {
            $role = Role::firstOrCreate(
                ['slug' => $r['slug']],
                [
                    'name' => $r['name'],
                    'description' => $r['description'],
                    'color' => $r['color'],
                    'scope' => 'platform',
                    'is_system' => $r['is_system'],
                ]
            );

            // Sync permissions for this role
            $permIds = [];
            foreach ($r['permissions'] as $slug) {
                if (isset($allPermissionModels[$slug])) {
                    $permIds[] = $allPermissionModels[$slug]->id;
                }
            }
            $role->permissions()->sync($permIds);
        }
    }
}

