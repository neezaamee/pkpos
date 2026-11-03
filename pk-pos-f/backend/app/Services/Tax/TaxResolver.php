<?php

namespace App\Services\Tax;

use App\Exceptions\TaxRuleNotFoundException;
use App\Models\TaxRule;
use App\Models\Tenant;
use Carbon\Carbon;

class TaxResolver
{
    /**
     * Resolve the applicable tax rule for a given category, province, payment method, and date.
     *
     * @throws TaxRuleNotFoundException
     */
    public function resolve(
        Tenant $tenant,
        string $taxCategory,
        string $province,
        string $paymentMethod,
        Carbon $saleDate
    ): TaxRule {
        $dateStr = $saleDate->toDateString();

        $rule = TaxRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tax_category', $taxCategory)
            ->where('is_active', true)
            ->where(function ($q) use ($province) {
                $q->where('province', $province)
                    ->orWhere('province', 'federal');
            })
            ->where(function ($q) use ($paymentMethod) {
                $q->where('payment_method', $paymentMethod)
                    ->orWhere('payment_method', 'any');
            })
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateStr);
            })
            ->orderByRaw('CASE WHEN payment_method = ? THEN 0 ELSE 1 END', [$paymentMethod])
            ->orderByRaw('CASE WHEN province = ? THEN 0 ELSE 1 END', [$province])
            ->orderByDesc('effective_from')
            ->first();

        if (! $rule) {
            throw new TaxRuleNotFoundException($taxCategory, $province, $paymentMethod);
        }

        return $rule;
    }
}
