<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MasterHsCode;
use App\Models\ProvincialTaxTemplate;
use App\Models\TaxRule;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminMasterDataController extends Controller
{
    /**
     * Display the Master HS Codes Catalog with search and category filters.
     */
    public function hsCodes(Request $request): Response
    {
        $search = $request->input('search');
        $taxCategory = $request->input('tax_category');
        $authority = $request->input('authority');

        $query = MasterHsCode::query()->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('hs_code', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        if ($taxCategory) {
            $query->where('tax_category', $taxCategory);
        }

        if ($authority) {
            $query->where('authority', $authority);
        }

        $hsCodes = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'hs_code' => $item->hs_code,
                'description' => $item->description,
                'tax_category' => $item->tax_category,
                'default_rate_basis_points' => $item->default_rate_basis_points,
                'default_rate_pct' => round($item->default_rate_basis_points / 100, 2),
                'authority' => $item->authority,
                'is_active' => (bool) $item->is_active,
                'updated_at' => $item->updated_at?->format('Y-m-d'),
            ]);

        return Inertia::render('SuperAdmin/MasterData/HsCodes', [
            'hsCodes' => $hsCodes,
            'filters' => [
                'search' => $search,
                'tax_category' => $taxCategory,
                'authority' => $authority,
            ],
        ]);
    }

    /**
     * Store a newly created Master HS code.
     */
    public function storeHsCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hs_code' => ['required', 'string', 'max:20', 'unique:master_hs_codes,hs_code'],
            'description' => ['required', 'string', 'max:1000'],
            'tax_category' => ['required', 'string', 'in:general_goods,tier_1_retail,reduced_goods,services,exempt'],
            'default_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'authority' => ['required', 'string', 'in:fbr,pra,srb,kpra,bra'],
            'is_active' => ['boolean'],
        ]);

        $code = MasterHsCode::create($validated);

        return back()->with('success', "Master HS Code '{$code->hs_code}' created successfully.");
    }

    /**
     * Update an existing Master HS code.
     */
    public function updateHsCode(Request $request, string $id): RedirectResponse
    {
        $code = MasterHsCode::findOrFail($id);

        $validated = $request->validate([
            'hs_code' => ['required', 'string', 'max:20', 'unique:master_hs_codes,hs_code,' . $code->id],
            'description' => ['required', 'string', 'max:1000'],
            'tax_category' => ['required', 'string', 'in:general_goods,tier_1_retail,reduced_goods,services,exempt'],
            'default_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'authority' => ['required', 'string', 'in:fbr,pra,srb,kpra,bra'],
            'is_active' => ['boolean'],
        ]);

        $code->update($validated);

        return back()->with('success', "Master HS Code '{$code->hs_code}' updated successfully.");
    }

    /**
     * Bulk import HS codes via CSV file upload or raw CSV data.
     */
    public function importHsCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'csv_data' => ['nullable', 'string'],
        ]);

        $csvString = '';
        if ($request->hasFile('csv_file')) {
            $csvString = file_get_contents($request->file('csv_file')->getRealPath());
        } elseif ($request->filled('csv_data')) {
            $csvString = $request->input('csv_data');
        } else {
            return back()->with('error', 'Please provide either a CSV file or paste CSV text data.');
        }

        $lines = array_filter(array_map('trim', explode("\n", $csvString)));
        if (count($lines) < 2) {
            return back()->with('error', 'CSV must contain at least a header row and one data row.');
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim(str_replace(['"', "'"], '', $h))), $header);

        $now = now();
        $chunk = [];
        $count = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($row) < count($header)) continue;

            $data = array_combine($header, array_slice($row, 0, count($header)));
            $hsCode = trim($data['hs_code'] ?? $data['pct_code'] ?? '');
            $desc = trim($data['description'] ?? '');

            if (empty($hsCode) || empty($desc)) continue;

            $chunk[] = [
                'id' => (string) Str::uuid(),
                'hs_code' => $hsCode,
                'description' => $desc,
                'tax_category' => $data['tax_category'] ?? 'general_goods',
                'default_rate_basis_points' => (int) ($data['default_rate_basis_points'] ?? 1800),
                'authority' => $data['authority'] ?? 'fbr',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $count++;

            if (count($chunk) >= 500) {
                MasterHsCode::upsert(
                    $chunk,
                    ['hs_code'],
                    ['description', 'tax_category', 'default_rate_basis_points', 'authority', 'is_active', 'updated_at']
                );
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            MasterHsCode::upsert(
                $chunk,
                ['hs_code'],
                ['description', 'tax_category', 'default_rate_basis_points', 'authority', 'is_active', 'updated_at']
            );
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'hs_codes_csv_imported',
            'auditable_type' => MasterHsCode::class,
            'auditable_id' => (string) Str::uuid(),
            'new_values' => ['imported_records_count' => $count],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Successfully imported {$count} HS Codes into the master catalog.");
    }

    /**
     * Display standard provincial and federal tax rate templates.
     */
    public function taxTemplates(Request $request): Response
    {
        $province = $request->input('province');

        $query = ProvincialTaxTemplate::query()->latest();

        if ($province) {
            $query->where('province', $province);
        }

        $templates = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'province' => $item->province,
                'name' => $item->name,
                'authority' => $item->authority,
                'tax_category' => $item->tax_category,
                'payment_method' => $item->payment_method,
                'rate_basis_points' => $item->rate_basis_points,
                'rate_pct' => round($item->rate_basis_points / 100, 2),
                'effective_from' => $item->effective_from?->format('Y-m-d') ?? '',
                'is_active' => (bool) $item->is_active,
                'description' => $item->description,
            ]);

        return Inertia::render('SuperAdmin/MasterData/TaxTemplates', [
            'templates' => $templates,
            'filters' => [
                'province' => $province,
            ],
        ]);
    }

    /**
     * Store a newly created provincial tax template.
     */
    public function storeTaxTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province' => ['required', 'string', 'in:federal,punjab,sindh,kp,balochistan'],
            'name' => ['required', 'string', 'max:150'],
            'authority' => ['required', 'string', 'in:fbr,pra,srb,kpra,bra'],
            'tax_category' => ['required', 'string', 'in:general_goods,tier_1_retail,reduced_goods,services,exempt'],
            'payment_method' => ['required', 'string', 'in:any,cash,card,raast,wallet'],
            'rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'effective_from' => ['required', 'date'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $tmpl = ProvincialTaxTemplate::create($validated);

        return back()->with('success', "Tax template '{$tmpl->name}' created successfully.");
    }

    /**
     * Update an existing provincial tax template.
     */
    public function updateTaxTemplate(Request $request, string $id): RedirectResponse
    {
        $tmpl = ProvincialTaxTemplate::findOrFail($id);

        $validated = $request->validate([
            'province' => ['required', 'string', 'in:federal,punjab,sindh,kp,balochistan'],
            'name' => ['required', 'string', 'max:150'],
            'authority' => ['required', 'string', 'in:fbr,pra,srb,kpra,bra'],
            'tax_category' => ['required', 'string', 'in:general_goods,tier_1_retail,reduced_goods,services,exempt'],
            'payment_method' => ['required', 'string', 'in:any,cash,card,raast,wallet'],
            'rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'effective_from' => ['required', 'date'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $tmpl->update($validated);

        return back()->with('success', "Tax template '{$tmpl->name}' updated successfully.");
    }

    /**
     * Synchronize a standard tax template directly into all matching merchant tenants.
     */
    public function syncTaxTemplateToTenants(Request $request, string $id): RedirectResponse
    {
        $tmpl = ProvincialTaxTemplate::findOrFail($id);

        $tenants = $tmpl->province === 'federal'
            ? Tenant::withoutGlobalScopes()->whereNull('suspended_at')->get()
            : Tenant::withoutGlobalScopes()->where('province', $tmpl->province)->whereNull('suspended_at')->get();

        $syncedCount = 0;
        foreach ($tenants as $t) {
            TaxRule::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $t->id,
                    'tax_category' => $tmpl->tax_category,
                    'payment_method' => $tmpl->payment_method,
                ],
                [
                    'name' => $tmpl->name,
                    'province' => $tmpl->province,
                    'rate_basis_points' => $tmpl->rate_basis_points,
                    'authority' => $tmpl->authority,
                    'effective_from' => $tmpl->effective_from->toDateString(),
                    'is_active' => $tmpl->is_active,
                ]
            );
            $syncedCount++;
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'tax_template_synced_to_tenants',
            'auditable_type' => ProvincialTaxTemplate::class,
            'auditable_id' => $tmpl->id,
            'new_values' => [
                'template_name' => $tmpl->name,
                'target_province' => $tmpl->province,
                'affected_tenants_count' => $syncedCount,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Successfully synchronized '{$tmpl->name}' across {$syncedCount} active tenants in {$tmpl->province}.");
    }

    /**
     * Lookup API for POS clients and web catalog auto-completion.
     */
    public function searchHsCodesApi(Request $request): JsonResponse
    {
        $query = $request->input('query');

        $results = MasterHsCode::where('is_active', true)
            ->when($query, function ($q, $query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('hs_code', 'ILIKE', "%{$query}%")
                        ->orWhere('description', 'ILIKE', "%{$query}%");
                });
            })
            ->take(15)
            ->get(['id', 'hs_code', 'description', 'tax_category', 'default_rate_basis_points', 'authority']);

        return response()->json($results);
    }
}

