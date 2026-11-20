<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptTemplate extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'business_name',
        'branch_name',
        'address_line1',
        'address_line2',
        'phone',
        'email',
        'website',
        'ntn_label',
        'strn_label',
        'pos_id_label',
        'paper_width',
        'show_tax_breakdown',
        'show_customer_info',
        'show_fbr_qr',
        'header_note',
        'footer_policy',
        'footer_greeting',
        'logo_url',
    ];

    protected function casts(): array
    {
        return [
            'show_tax_breakdown' => 'boolean',
            'show_customer_info' => 'boolean',
            'show_fbr_qr' => 'boolean',
        ];
    }

    /**
     * Get or create default receipt template for a tenant.
     */
    public static function forTenant(Tenant|string $tenant): self
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant);

        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantModel->id],
            [
                'business_name' => $tenantModel->name,
                'branch_name' => 'Main Branch',
                'address_line1' => 'Commercial Area',
                'phone' => '021-111-222-333',
                'ntn_label' => 'NTN:',
                'strn_label' => 'STRN:',
                'pos_id_label' => 'FBR POS ID:',
                'paper_width' => '80mm',
                'show_tax_breakdown' => true,
                'show_customer_info' => true,
                'show_fbr_qr' => true,
                'header_note' => 'Official Fiscal Receipt',
                'footer_policy' => 'Goods once sold can only be exchanged within 3 days with original receipt.',
                'footer_greeting' => 'Thank you for shopping with us! JazakAllah Khair.',
            ]
        );
    }
}
