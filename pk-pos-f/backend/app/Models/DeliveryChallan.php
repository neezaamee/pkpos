<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'sale_id',
        'contractor_project_id',
        'challan_number',
        'gate_pass_number',
        'vehicle_number',
        'vehicle_type',
        'driver_name',
        'driver_phone',
        'transporter_name',
        'freight_minor',
        'freight_payment_type',
        'destination_site',
        'receiver_name',
        'receiver_phone',
        'status',
        'dispatched_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'freight_minor' => 'integer',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $appends = [
        'freight_terms',
        'freight_amount_minor',
        'site_destination',
    ];

    public function getFreightTermsAttribute(): string
    {
        return $this->attributes['freight_payment_type'] ?? 'to_pay_customer';
    }

    public function setFreightTermsAttribute($value): void
    {
        $this->attributes['freight_payment_type'] = $value;
    }

    public function getFreightAmountMinorAttribute(): int
    {
        return (int) ($this->attributes['freight_minor'] ?? 0);
    }

    public function setFreightAmountMinorAttribute($value): void
    {
        $this->attributes['freight_minor'] = (int) $value;
    }

    public function getSiteDestinationAttribute(): ?string
    {
        return $this->attributes['destination_site'] ?? null;
    }

    public function setSiteDestinationAttribute($value): void
    {
        $this->attributes['destination_site'] = $value;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function contractorProject(): BelongsTo
    {
        return $this->belongsTo(ContractorProject::class, 'contractor_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class, 'delivery_challan_id');
    }

    public function markDispatched(): void
    {
        $this->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
