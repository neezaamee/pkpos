<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalRetentionArchive extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fiscal_retention_archives';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'archive_code',
        'tenant_id',
        'financial_year',
        'record_count',
        'file_size_bytes',
        'sha256_checksum',
        'storage_path',
        'status',
        'statutory_retention_until',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'file_size_bytes' => 'integer',
            'statutory_retention_until' => 'date',
        ];
    }

    /**
     * Target merchant tenant if scoped to a specific business.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Staff compliance officer who compiled the retention package.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope ready archives.
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    /**
     * Generate an incremental unique archive code: e.g. ARC-FY25-26-0001.
     */
    public static function generateArchiveCode(string $financialYear): string
    {
        if (preg_match('/^20(\d{2})-20(\d{2})$/', $financialYear, $matches)) {
            $fySlug = $matches[1] . $matches[2];
        } else {
            $fySlug = str_replace(['-', ' '], '', $financialYear);
        }

        $count = static::where('financial_year', $financialYear)->count() + 1;

        return sprintf('ARC-FY%s-%04d', $fySlug, $count);
    }
}
