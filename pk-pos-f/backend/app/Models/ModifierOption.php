<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'modifier_group_id',
        'name',
        'price_override_minor',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price_override_minor' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }
}
