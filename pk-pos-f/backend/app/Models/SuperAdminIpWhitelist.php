<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\IpUtils;

class SuperAdminIpWhitelist extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'super_admin_ip_whitelists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ip_address',
        'label',
        'is_active',
        'created_by',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who registered this whitelist entry.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if a client IP address is allowed access under the current whitelist policy.
     *
     * @param string $clientIp
     * @return bool
     */
    public static function isIpAllowed(string $clientIp): bool
    {
        // If whitelisting table has no active records, access is unrestricted
        $activeEntries = static::where('is_active', true)->pluck('ip_address')->toArray();
        if (empty($activeEntries)) {
            return true;
        }

        // Always allow loopback in local development or testing environments
        if (in_array(app()->environment(), ['local', 'testing']) && in_array($clientIp, ['127.0.0.1', '::1'])) {
            return true;
        }

        foreach ($activeEntries as $allowedRange) {
            $allowedRange = trim($allowedRange);
            if ($allowedRange === $clientIp) {
                return true;
            }

            // Check CIDR or IP range using Symfony IpUtils
            if (IpUtils::checkIp($clientIp, $allowedRange)) {
                return true;
            }
        }

        return false;
    }
}
