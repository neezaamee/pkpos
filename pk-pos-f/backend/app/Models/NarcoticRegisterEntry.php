<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NarcoticRegisterEntry extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'prescription_id',
        'sale_id',
        'product_id',
        'batch_id',
        'batch_number',
        'quantity_dispensed',
        'unit_code',
        'patient_name',
        'patient_cnic',
        'doctor_name',
        'doctor_pmdc_number',
        'dispensed_by_user_id',
        'dispensed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_dispensed' => 'float',
            'dispensed_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }
}
