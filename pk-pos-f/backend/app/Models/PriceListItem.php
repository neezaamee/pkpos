<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'price_list_items';

    protected $fillable = [
        'id',
        'tenant_id',
        'price_list_id',
        'product_id',
        'min_quantity',
        'price_minor',
        'discount_pct',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'float',
            'price_minor' => 'integer',
            'discount_pct' => 'float',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
