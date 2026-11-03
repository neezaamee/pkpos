<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, BelongsToTenant, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'super_admin',
        'staff_role',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_enforced',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'super_admin',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'super_admin' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_enforced' => 'boolean',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Check if user is an internal platform staff member.
     */
    public function isStaff(): bool
    {
        return (bool) $this->super_admin;
    }

    /**
     * Check if user is a full Super Admin.
     */
    public function isSuperAdminRole(): bool
    {
        return $this->super_admin && ($this->staff_role === 'super_admin' || is_null($this->staff_role));
    }

    /**
     * Check if user is a Support Agent.
     */
    public function isSupportAgent(): bool
    {
        return $this->super_admin && $this->staff_role === 'support_agent';
    }

    /**
     * Check if user is a Compliance Auditor.
     */
    public function isComplianceAuditor(): bool
    {
        return $this->super_admin && $this->staff_role === 'compliance_auditor';
    }

    /**
     * Check if user holds one of the specified internal staff roles.
     *
     * @param  array<string>|string  $roles
     */
    public function hasStaffRole(array|string $roles): bool
    {
        if (! $this->super_admin) {
            return false;
        }

        $roles = (array) $roles;
        $currentRole = $this->staff_role ?: 'super_admin';

        if (in_array($currentRole, $roles, true)) {
            return true;
        }

        // Check assigned roles relationship
        return $this->roles->pluck('slug')->intersect($roles)->isNotEmpty();
    }

    /**
     * The roles assigned to this user.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * Check if user possesses a specific granular permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if (! $this->super_admin) {
            return false;
        }

        // Super Admin role automatically possesses all permissions
        if ($this->staff_role === 'super_admin' || is_null($this->staff_role)) {
            return true;
        }

        // Check if assigned roles have this permission
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->with('permissions')->get();
        foreach ($roles as $role) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }

        // Fallback: check seeded role matching $this->staff_role
        if ($this->staff_role) {
            $matchingRole = Role::where('slug', $this->staff_role)->with('permissions')->first();
            if ($matchingRole && $matchingRole->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assign a role to this user.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Check if 2FA is active.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Generate standard Base32 secret string for TOTP.
     */
    public static function generateBase32Secret(int $length = 16): string
    {
        return app(\App\Services\Security\TotpService::class)->generateBase32Secret($length);
    }

    /**
     * Verify a 6-digit TOTP code against secret.
     */
    public function verifyTwoFactorCode(string $code, ?string $secret = null): bool
    {
        $secret = $secret ?: $this->two_factor_secret;
        if (! $secret) {
            return false;
        }

        return app(\App\Services\Security\TotpService::class)->verifyCode($secret, $code);
    }

    /**
     * Calculate TOTP code for a time slice using standard Base32 decoding and HMAC-SHA1.
     */
    public function calculateTotp(string $secret, int $timeSlice): string
    {
        return app(\App\Services\Security\TotpService::class)->calculateTotp($secret, $timeSlice);
    }

    /**
     * Generate set of random 8-character recovery codes.
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        return app(\App\Services\Security\TotpService::class)->generateRecoveryCodes($count);
    }
}
