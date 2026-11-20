<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorProject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'customer_id',
        'name',
        'site_address',
        'plot_number',
        'city',
        'supervisor_name',
        'supervisor_phone',
        'credit_limit_minor',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_limit_minor' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'contractor_project_id');
    }

    public function deliveryChallans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class, 'contractor_project_id');
    }

    public function getProjectBalanceMinorAttribute(): int
    {
        return (int) $this->sales()
            ->where('payment_method', 'credit')
            ->sum('total_minor');
    }
}
