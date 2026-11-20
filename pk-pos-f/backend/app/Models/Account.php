<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'code',
        'name',
        'type',
        'sub_type',
        'normal_balance',
        'is_system',
        'is_active',
        'currency',
        'opening_balance_minor',
        'current_balance_minor',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'opening_balance_minor' => 'integer',
            'current_balance_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Recalculate and update current balance from posted journal lines.
     */
    public function recalculateBalance(): int
    {
        $debitSum = (int) $this->journalLines()
            ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted'))
            ->sum('debit_minor');

        $creditSum = (int) $this->journalLines()
            ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted'))
            ->sum('credit_minor');

        if ($this->isDebitNormal()) {
            $balance = $this->opening_balance_minor + ($debitSum - $creditSum);
        } else {
            $balance = $this->opening_balance_minor + ($creditSum - $debitSum);
        }

        $this->current_balance_minor = $balance;
        $this->saveQuietly();

        return $balance;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
