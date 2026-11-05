<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicOnboardingController;
use App\Http\Controllers\FbrCredentialsController;
use App\Http\Controllers\InventoryWebController;
use App\Http\Controllers\ProductWebController;
use App\Http\Controllers\SalesDashboardController;
use App\Http\Controllers\TaxRuleWebController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuditController;
use App\Http\Controllers\SuperAdmin\SuperAdminBackupController;
use App\Http\Controllers\SuperAdmin\SuperAdminBillingController;
use App\Http\Controllers\SuperAdmin\SuperAdminBroadcastController;
use App\Http\Controllers\SuperAdmin\SuperAdminComplianceController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SuperAdminMasterDataController;
use App\Http\Controllers\SuperAdmin\SuperAdminOnboardingController;
use App\Http\Controllers\SuperAdmin\SuperAdminPlanController;
use App\Http\Controllers\SuperAdmin\SuperAdminReleaseController;
use App\Http\Controllers\SuperAdmin\SuperAdminRoleController;
use App\Http\Controllers\SuperAdmin\SuperAdminSecurityController;
use App\Http\Controllers\SuperAdmin\SuperAdminStaffController;
use App\Http\Controllers\SuperAdmin\SuperAdminSupportController;
use App\Http\Controllers\SuperAdmin\SuperAdminSystemController;
use App\Http\Controllers\SuperAdmin\SuperAdminTenantController;
use App\Http\Controllers\SuperAdmin\SuperAdminTerminalController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return Auth::user()->super_admin
            ? redirect()->route('superadmin.dashboard')
            : redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Public Merchant Self-Registration & Onboarding Application
Route::get('/apply', [PublicOnboardingController::class, 'create'])->name('apply');
Route::post('/apply', [PublicOnboardingController::class, 'store'])->name('apply.store');

// Desktop POS Client Installer Download
Route::get('/downloads/desktop', [\App\Http\Controllers\Api\V1\ReleaseApiController::class, 'downloadLatestDesktop'])->name('downloads.desktop');

// Super Admin platform routes
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    // Tenant Mutations & Locations (Super Admin Only)
    Route::middleware('staff_role:super_admin')->group(function () {
        Route::get('/tenants/create', [SuperAdminTenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [SuperAdminTenantController::class, 'store'])->name('tenants.store');
        Route::post('/tenants/{id}/suspend', [SuperAdminTenantController::class, 'toggleSuspend'])->name('tenants.suspend')->whereUuid('id');
        Route::post('/tenants/{id}/locations', [SuperAdminTenantController::class, 'storeLocation'])->name('tenants.locations.store')->whereUuid('id');
    });

    // Tenant Directory & Details (Accessible to all internal staff)
    Route::get('/tenants', [SuperAdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{id}', [SuperAdminTenantController::class, 'show'])->name('tenants.show')->whereUuid('id');

    // Tenant Profile & Staff Reset (Super Admin & Support Agent)
    Route::middleware('staff_role:super_admin,support_agent')->group(function () {
        Route::get('/tenants/{id}/edit', [SuperAdminTenantController::class, 'edit'])->name('tenants.edit')->whereUuid('id');
        Route::put('/tenants/{id}', [SuperAdminTenantController::class, 'update'])->name('tenants.update')->whereUuid('id');
        Route::post('/tenants/{id}/users', [SuperAdminTenantController::class, 'storeUser'])->name('tenants.users.store')->whereUuid('id');
        Route::delete('/tenants/{id}/users/{userId}', [SuperAdminTenantController::class, 'destroyUser'])->name('tenants.users.destroy')->whereUuid(['id', 'userId']);
        Route::post('/tenants/{id}/users/{userId}/reset-password', [SuperAdminTenantController::class, 'resetUserPassword'])->name('tenants.users.reset-password')->whereUuid(['id', 'userId']);
    });

    // Platform Audit Logging (Super Admin & Compliance Auditor)
    Route::middleware('staff_role:super_admin,compliance_auditor')->group(function () {
        Route::get('/audit-logs', [SuperAdminAuditController::class, 'index'])->name('audit.index');
    });

    // FBR Fiscalization & Compliance Command Center
    Route::middleware('staff_role:super_admin,compliance_auditor')->group(function () {
        Route::get('/compliance/fbr', [SuperAdminComplianceController::class, 'overview'])->name('compliance.fbr.overview');
        Route::get('/compliance/fbr/failed', [SuperAdminComplianceController::class, 'failedInvoices'])->name('compliance.fbr.failed');
        Route::get('/compliance/fbr/provisional', [SuperAdminComplianceController::class, 'provisionalReceipts'])->name('compliance.fbr.provisional');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/compliance/fbr/retry/{saleId}', [SuperAdminComplianceController::class, 'retrySale'])->name('compliance.fbr.retry');
        Route::post('/compliance/fbr/bulk-retry', [SuperAdminComplianceController::class, 'bulkRetry'])->name('compliance.fbr.bulk-retry');
        Route::post('/compliance/fbr/ping', [SuperAdminComplianceController::class, 'pingFbr'])->name('compliance.fbr.ping');
    });

    // Master Data & FBR HS Codes Repository
    Route::middleware('staff_role:super_admin,compliance_auditor')->group(function () {
        Route::get('/master-data/hs-codes', [SuperAdminMasterDataController::class, 'hsCodes'])->name('master-data.hs-codes.index');
        Route::get('/master-data/tax-templates', [SuperAdminMasterDataController::class, 'taxTemplates'])->name('master-data.tax-templates.index');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/master-data/hs-codes', [SuperAdminMasterDataController::class, 'storeHsCode'])->name('master-data.hs-codes.store');
        Route::put('/master-data/hs-codes/{id}', [SuperAdminMasterDataController::class, 'updateHsCode'])->name('master-data.hs-codes.update');
        Route::post('/master-data/hs-codes/import', [SuperAdminMasterDataController::class, 'importHsCodes'])->name('master-data.hs-codes.import');
        Route::post('/master-data/tax-templates', [SuperAdminMasterDataController::class, 'storeTaxTemplate'])->name('master-data.tax-templates.store');
        Route::put('/master-data/tax-templates/{id}', [SuperAdminMasterDataController::class, 'updateTaxTemplate'])->name('master-data.tax-templates.update');
        Route::post('/master-data/tax-templates/{id}/sync', [SuperAdminMasterDataController::class, 'syncTaxTemplateToTenants'])->name('master-data.tax-templates.sync');
    });

    // POS Terminal Fleet Management
    Route::middleware('staff_role:super_admin,support_agent')->group(function () {
        Route::get('/terminals', [SuperAdminTerminalController::class, 'index'])->name('terminals.index');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/terminals/pairing-code', [SuperAdminTerminalController::class, 'generatePairingCode'])->name('terminals.pairing-code');
        Route::put('/terminals/{id}', [SuperAdminTerminalController::class, 'update'])->name('terminals.update');
        Route::post('/terminals/{id}/revoke', [SuperAdminTerminalController::class, 'revoke'])->name('terminals.revoke');
        Route::post('/terminals/{id}/reactivate', [SuperAdminTerminalController::class, 'reactivate'])->name('terminals.reactivate');
        Route::delete('/terminals/{id}', [SuperAdminTerminalController::class, 'destroy'])->name('terminals.destroy');
    });

    // Releases & Version Control
    Route::middleware('staff_role:super_admin,support_agent')->group(function () {
        Route::get('/releases', [SuperAdminReleaseController::class, 'index'])->name('releases.index');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/releases', [SuperAdminReleaseController::class, 'store'])->name('releases.store');
        Route::put('/releases/{id}', [SuperAdminReleaseController::class, 'update'])->name('releases.update');
        Route::post('/releases/{id}/publish', [SuperAdminReleaseController::class, 'togglePublish'])->name('releases.publish');
        Route::delete('/releases/{id}', [SuperAdminReleaseController::class, 'destroy'])->name('releases.destroy');
    });

    // SaaS Subscriptions & Plans (Super Admin Only)
    Route::middleware('staff_role:super_admin')->group(function () {
        Route::get('/plans', [SuperAdminPlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [SuperAdminPlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{id}', [SuperAdminPlanController::class, 'update'])->name('plans.update');
        Route::post('/subscriptions/assign', [SuperAdminPlanController::class, 'assignSubscription'])->name('subscriptions.assign');
        Route::post('/subscriptions/{id}/cancel', [SuperAdminPlanController::class, 'cancelSubscription'])->name('subscriptions.cancel');
    });

    // Pakistan SaaS Payment Invoicing & B2B Billing Gateway (Super Admin & Billing Specialist)
    Route::middleware('staff_role:super_admin,billing_specialist')->group(function () {
        Route::get('/billing', [SuperAdminBillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/invoices', [SuperAdminBillingController::class, 'store'])->name('billing.invoices.store');
        Route::post('/billing/invoices/{id}/pay-wire', [SuperAdminBillingController::class, 'submitBankWire'])->name('billing.invoices.pay-wire');
        Route::post('/billing/transactions/{id}/review', [SuperAdminBillingController::class, 'reviewPayment'])->name('billing.transactions.review');
        Route::post('/billing/invoices/{id}/void', [SuperAdminBillingController::class, 'voidInvoice'])->name('billing.invoices.void');
    });

    // Customer Support & Helpdesk Hub (Super Admin & Support Agent)
    Route::middleware('staff_role:super_admin,support_agent')->group(function () {
        Route::get('/support/tickets', [SuperAdminSupportController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/{id}', [SuperAdminSupportController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{id}/reply', [SuperAdminSupportController::class, 'reply'])->name('support.tickets.reply');
        Route::post('/support/tickets/{id}/status', [SuperAdminSupportController::class, 'updateStatus'])->name('support.tickets.status');
        Route::post('/support/tickets/{id}/assign', [SuperAdminSupportController::class, 'assignStaff'])->name('support.tickets.assign');
        Route::get('/support/knowledge-base', [SuperAdminSupportController::class, 'knowledgeBase'])->name('support.knowledge-base');
        Route::post('/impersonate/{userId}', [ImpersonationController::class, 'impersonate'])->name('impersonate');
    });

    // System Diagnostics & Background Queue Telemetry (Super Admin Only)
    Route::middleware('staff_role:super_admin')->group(function () {
        Route::get('/system/health', [SuperAdminSystemController::class, 'health'])->name('system.health');
        Route::get('/system/jobs', [SuperAdminSystemController::class, 'jobs'])->name('system.jobs');
        Route::post('/system/jobs/{id}/retry', [SuperAdminSystemController::class, 'retryJob'])->name('system.jobs.retry');
        Route::post('/system/jobs/retry-all', [SuperAdminSystemController::class, 'retryAllJobs'])->name('system.jobs.retry-all');
        Route::delete('/system/jobs/{id}', [SuperAdminSystemController::class, 'deleteJob'])->name('system.jobs.delete');
        Route::post('/system/jobs/flush', [SuperAdminSystemController::class, 'flushJobs'])->name('system.jobs.flush');
    });

    // Automated Backups & Fiscal Data Archiving (6-Year Statutory Retention)
    Route::middleware('staff_role:super_admin,compliance_auditor,release_engineer')->group(function () {
        Route::get('/backups', [SuperAdminBackupController::class, 'index'])->name('backups.index');
        Route::get('/backups/fiscal-archive/{id}/download', [SuperAdminBackupController::class, 'downloadArchive'])->name('backups.fiscal-archive.download');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/backups/snapshot', [SuperAdminBackupController::class, 'createBackup'])->name('backups.snapshot');
        Route::post('/backups/fiscal-archive', [SuperAdminBackupController::class, 'generateFiscalArchive'])->name('backups.fiscal-archive');
        Route::delete('/backups/{id}', [SuperAdminBackupController::class, 'destroyBackup'])->name('backups.destroy');
    });

    // Internal Staff Directory & RBAC (Super Admin Only)
    Route::middleware('staff_role:super_admin')->group(function () {
        Route::get('/staff', [SuperAdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [SuperAdminStaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{id}', [SuperAdminStaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/{id}/reset-password', [SuperAdminStaffController::class, 'resetPassword'])->name('staff.reset-password');
        Route::post('/staff/{id}/toggle-status', [SuperAdminStaffController::class, 'toggleStatus'])->name('staff.toggle-status');
        Route::delete('/staff/{id}', [SuperAdminStaffController::class, 'destroy'])->name('staff.destroy');

        // Dynamic Roles & Granular Permissions Engine
        Route::get('/roles', [SuperAdminRoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [SuperAdminRoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{id}', [SuperAdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [SuperAdminRoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Platform Broadcasts, Emergency Banners & Merchant Notifications
    Route::middleware('staff_role:super_admin,support_agent,compliance_auditor,billing_specialist,release_engineer')->group(function () {
        Route::get('/broadcasts', [SuperAdminBroadcastController::class, 'index'])->name('broadcasts.index');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/broadcasts', [SuperAdminBroadcastController::class, 'store'])->name('broadcasts.store');
        Route::put('/broadcasts/{id}', [SuperAdminBroadcastController::class, 'update'])->name('broadcasts.update');
        Route::post('/broadcasts/{id}/toggle-active', [SuperAdminBroadcastController::class, 'toggleActive'])->name('broadcasts.toggle-active');
        Route::delete('/broadcasts/{id}', [SuperAdminBroadcastController::class, 'destroy'])->name('broadcasts.destroy');
    });

    // Public Merchant Self-Registration & Onboarding Approval Queue
    Route::middleware('staff_role:super_admin,compliance_auditor,support_agent')->group(function () {
        Route::get('/onboarding', [SuperAdminOnboardingController::class, 'index'])->name('onboarding.index');
        Route::get('/onboarding/{id}', [SuperAdminOnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/onboarding/{id}/check-atl', [SuperAdminOnboardingController::class, 'checkAtl'])->name('onboarding.check-atl');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/onboarding/{id}/approve', [SuperAdminOnboardingController::class, 'approveAndProvision'])->name('onboarding.approve');
        Route::post('/onboarding/{id}/reject', [SuperAdminOnboardingController::class, 'reject'])->name('onboarding.reject');
    });

    // Platform Security Hardening & Session Killswitch
    Route::middleware('staff_role:super_admin,support_agent,compliance_auditor,billing_specialist,release_engineer')->group(function () {
        Route::get('/security', [SuperAdminSecurityController::class, 'index'])->name('security.index');
        Route::post('/security/2fa/setup', [SuperAdminSecurityController::class, 'setup2fa'])->name('security.2fa.setup');
        Route::post('/security/2fa/confirm', [SuperAdminSecurityController::class, 'confirm2fa'])->name('security.2fa.confirm');
        Route::post('/security/2fa/disable', [SuperAdminSecurityController::class, 'disable2fa'])->name('security.2fa.disable');
    });

    Route::middleware('staff_role:super_admin')->group(function () {
        Route::post('/security/sessions/{id}/kill', [SuperAdminSecurityController::class, 'killSession'])->name('security.sessions.kill');
        Route::post('/security/sessions/kill-all', [SuperAdminSecurityController::class, 'killAllSessions'])->name('security.sessions.kill-all');
        Route::post('/security/enforce-2fa', [SuperAdminSecurityController::class, 'toggleEnforceStaff2fa'])->name('security.enforce-2fa');
    });
});

// Return from impersonation (accessible while impersonating a tenant user)
Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/superadmin/impersonate/leave', [ImpersonationController::class, 'leave'])
        ->name('superadmin.impersonate.leave');
});

// Protected tenant admin routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/sales', SalesDashboardController::class)->name('sales');

    Route::get('/fbr-credentials', [FbrCredentialsController::class, 'index'])->name('fbr-credentials.index');
    Route::post('/fbr-credentials', [FbrCredentialsController::class, 'store'])->name('fbr-credentials.store');

    // Catalog & Tax Rules
    Route::get('/products', [ProductWebController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductWebController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductWebController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit', [ProductWebController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}', [ProductWebController::class, 'update'])->name('products.update');

    Route::get('/tax-rules', [TaxRuleWebController::class, 'index'])->name('tax-rules.index');
    Route::get('/tax-rules/create', [TaxRuleWebController::class, 'create'])->name('tax-rules.create');
    Route::post('/tax-rules', [TaxRuleWebController::class, 'store'])->name('tax-rules.store');
    Route::put('/tax-rules/{id}', [TaxRuleWebController::class, 'update'])->name('tax-rules.update');

    // Inventory & Stock Management
    Route::get('/inventory/levels', [InventoryWebController::class, 'levels'])->name('inventory.levels');
    Route::get('/inventory/movements', [InventoryWebController::class, 'movements'])->name('inventory.movements');
    Route::get('/inventory/adjust', [InventoryWebController::class, 'adjust'])->name('inventory.adjust');
    Route::post('/inventory/adjust', [InventoryWebController::class, 'storeAdjust'])->name('inventory.adjust.store');
});
