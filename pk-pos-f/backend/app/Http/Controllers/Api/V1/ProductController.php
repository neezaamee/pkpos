<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TaxRuleNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Services\Tax\TaxResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        protected TaxResolver $taxResolver
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $perPage = min((int) $request->input('per_page', 15), 100);

        $query = Product::with('variants')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%")
                        ->orWhere('barcode', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('tax_category'), function ($q) use ($request) {
                $q->where('tax_category', $request->input('tax_category'));
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->latest('created_at');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created product with optional variants in a single transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'hs_code' => ['required', 'regex:/^[0-9]{8}$/'],
            'unit' => ['nullable', Rule::in(['piece', 'kg', 'gram', 'litre', 'ml', 'hour', 'session'])],
            'price_minor' => ['required', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'tax_category' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'product_meta' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:50'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.price_override_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $validated['name'],
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'hs_code' => $validated['hs_code'],
                'unit' => $validated['unit'] ?? 'piece',
                'price_minor' => $validated['price_minor'],
                'cost_minor' => $validated['cost_minor'] ?? null,
                'tax_category' => $validated['tax_category'],
                'is_active' => $validated['is_active'] ?? true,
                'product_meta' => $validated['product_meta'] ?? null,
            ]);

            if (! empty($validated['variants'])) {
                foreach ($validated['variants'] as $variantData) {
                    ProductVariant::create([
                        'tenant_id' => $request->user()->tenant_id,
                        'product_id' => $product->id,
                        'name' => $variantData['name'],
                        'sku' => $variantData['sku'] ?? null,
                        'price_override_minor' => $variantData['price_override_minor'] ?? null,
                    ]);
                }
            }

            return $product->load('variants');
        });

        return response()->json($product, 201);
    }

    /**
     * Display the specified product with variants and tax rate preview.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeAccess($request);

        $product = Product::with('variants')->findOrFail($id);

        // Preview resolved tax rate for tenant's default location province + cash
        $user = $request->user();
        $tenant = $user->tenant;
        $location = Location::where('tenant_id', $user->tenant_id)->first();
        $province = $location?->province ?? $tenant->province ?? 'federal';

        $taxPreview = null;
        try {
            $rule = $this->taxResolver->resolve(
                $tenant,
                $product->tax_category,
                $province,
                'cash',
                Carbon::now()
            );

            $taxPreview = [
                'province' => $province,
                'payment_method' => 'cash',
                'rate_basis_points' => $rule->rate_basis_points,
                'rate_percentage' => number_format($rule->rate_basis_points / 100, 2) . '%',
                'authority' => $rule->authority,
                'rule_name' => $rule->name,
            ];
        } catch (TaxRuleNotFoundException $e) {
            $taxPreview = [
                'province' => $province,
                'payment_method' => 'cash',
                'rate_basis_points' => null,
                'rate_percentage' => null,
                'authority' => null,
                'rule_name' => null,
                'message' => 'No active tax rule resolved for default cash sale.',
            ];
        }

        return response()->json([
            'product' => $product,
            'tax_preview' => $taxPreview,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAccess($request);

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'hs_code' => ['sometimes', 'required', 'regex:/^[0-9]{8}$/'],
            'unit' => ['sometimes', 'required', Rule::in(['piece', 'kg', 'gram', 'litre', 'ml', 'hour', 'session'])],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'tax_category' => ['sometimes', 'required', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'product_meta' => ['nullable', 'array'],
        ]);

        // Prevent changing hs_code if any sale_items reference this product_id
        if (isset($validated['hs_code']) && $validated['hs_code'] !== $product->hs_code) {
            $isReferenced = SaleItem::where('product_id', $product->id)->exists();
            if ($isReferenced) {
                return response()->json([
                    'message' => 'Cannot change HS code for a product referenced in completed sales.',
                    'errors' => [
                        'hs_code' => ['Cannot change HS code for a product referenced in completed sales.'],
                    ],
                ], 422);
            }
        }

        $product->update($validated);

        return response()->json($product->fresh('variants'));
    }

    /**
     * Pull-based incremental catalog sync for POS desktop clients.
     */
    public function sync(Request $request): JsonResponse
    {
        $cursor = $request->query('updated_after', $request->query('server_updated_at'));

        $tenant = null;
        if ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            $tokenable = $token?->tokenable;
            $tenant = $tokenable instanceof \App\Models\Tenant ? $tokenable : $tokenable?->tenant;
        }
        $tenant = $tenant ?? $request->user()?->tenant ?? \App\Models\Tenant::first();

        $query = $tenant ? $tenant->products()->where('is_active', true) : Product::where('is_active', true);

        if ($cursor) {
            $query->where('updated_at', '>', Carbon::parse($cursor));
        }

        $products = $query->orderBy('updated_at', 'asc')->get();
        $latestUpdated = $products->max('updated_at')?->toIso8601String() ?? $cursor ?? now()->toIso8601String();

        $location = $tenant ? $tenant->locations()->first() : Location::first();
        $province = $tenant?->province ?? 'Punjab';

        $items = $products->map(function ($product) use ($tenant, $province) {
            $rate = 1800;
            try {
                $rule = $this->taxResolver->resolve(
                    tenant: $tenant,
                    province: $province,
                    taxCategory: $product->tax_category,
                    paymentMethod: 'cash',
                    saleDate: Carbon::now()
                );
                $rate = $rule->rate_basis_points;
            } catch (\Exception $e) {
                // fallback to standard rate
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'hs_code' => $product->hs_code,
                'price_minor' => $product->price_minor,
                'tax_category' => $product->tax_category,
                'unit' => $product->unit,
                'tax_rate_basis_points' => $rate,
                'updated_at' => $product->updated_at->toIso8601String(),
            ];
        });

        $cred = $tenant?->fbrCredential;

        return response()->json([
            'products' => $items,
            'server_updated_at' => $latestUpdated,
            'tenant' => [
                'name' => $tenant->name,
                'ntn' => $cred?->ntn ?? $tenant->ntn ?? '1234567-8',
                'strn' => $cred?->strn ?? $tenant->strn ?? '12-34-5678-001-99',
                'fbr_pos_id' => $cred?->pos_id ?? $tenant->fbr_pos_id ?? 'POS-001',
                'fbr_bearer_token' => $cred?->bearer_token ?? '',
                'address' => $location?->name ?? 'Main Branch',
                'province' => $province,
            ],
        ]);
    }

    /**
     * Authorize that the authenticated user is an owner or manager.
     */
    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
