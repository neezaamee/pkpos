<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'entry_number',
        'entry_date',
        'source_type',
        'source_id',
        'reference',
        'description',
        'status',
        'total_debit_minor',
        'total_credit_minor',
        'posted_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'total_debit_minor' => 'integer',
            'total_credit_minor' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('created_at', 'asc');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isBalanced(): bool
    {
        return $this->total_debit_minor === $this->total_credit_minor && $this->total_debit_minor > 0;
    }

    /**
     * Recalculates totals from lines and posts the journal entry, updating account balances.
     */
    public function post(): self
    {
        $debitSum = (int) $this->lines()->sum('debit_minor');
        $creditSum = (int) $this->lines()->sum('credit_minor');

        if ($debitSum !== $creditSum) {
            throw new DomainException("Cannot post unbalanced journal entry. Total debits ({$debitSum}) must equal total credits ({$creditSum}).");
        }

        if ($debitSum <= 0) {
            throw new DomainException("Cannot post zero-amount journal entry.");
        }

        $this->total_debit_minor = $debitSum;
        $this->total_credit_minor = $creditSum;
        $this->status = 'posted';
        $this->posted_at = now();
        $this->save();

        // Update current balances for all touched accounts
        $accountIds = $this->lines()->pluck('account_id')->unique();
        foreach (Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get() as $account) {
            $account->recalculateBalance();
        }

        return $this;
    }

    /**
     * Void this journal entry and recalculate touched account balances.
     */
    public function void(): self
    {
        if ($this->status === 'voided') {
            return $this;
        }

        $this->status = 'voided';
        $this->save();

        $accountIds = $this->lines()->pluck('account_id')->unique();
        foreach (Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get() as $account) {
            $account->recalculateBalance();
        }

        return $this;
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }
}
