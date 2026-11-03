<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminSecurityController extends Controller
{
    /**
     * Display the Platform Security Hardening & Session Killswitch Terminal.
     */
    public function index(Request $request): Response
    {
        $currentSessionId = session()->getId();

        $rawSessions = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email',
                'users.role as user_role',
                'users.staff_role',
                'users.super_admin',
            ])
            ->orderByDesc('sessions.last_activity')
            ->limit(100)
            ->get();

        $sessions = $rawSessions->map(function ($s) use ($currentSessionId) {
            return [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'browser' => $this->parseBrowser($s->user_agent),
                'last_activity_human' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'last_activity_at' => Carbon::createFromTimestamp($s->last_activity)->toIso8601String(),
                'is_current' => $s->id === $currentSessionId,
                'user' => $s->user_id ? [
                    'name' => $s->user_name,
                    'email' => $s->user_email,
                    'role' => $s->user_role,
                    'staff_role' => $s->staff_role,
                    'is_staff' => (bool) $s->super_admin,
                ] : null,
            ];
        });

        $staffCount = User::where('super_admin', true)->count();
        $staff2faCount = User::where('super_admin', true)->whereNotNull('two_factor_confirmed_at')->count();
        $isPolicyEnforced = User::where('super_admin', true)->where('two_factor_enforced', true)->exists();

        $metrics = [
            'active_sessions_count' => DB::table('sessions')->count(),
            'staff_count' => $staffCount,
            'staff_2fa_count' => $staff2faCount,
            'adoption_percentage' => $staffCount > 0 ? round(($staff2faCount / $staffCount) * 100, 1) : 0,
            'is_mandatory_enforced' => $isPolicyEnforced,
        ];

        /** @var User $currentUser */
        $currentUser = Auth::user();

        return Inertia::render('SuperAdmin/Security/Index', [
            'sessions' => $sessions,
            'metrics' => $metrics,
            'user2fa' => [
                'is_enabled' => $currentUser->hasTwoFactorEnabled(),
                'confirmed_at' => $currentUser->two_factor_confirmed_at?->toIso8601String(),
                'secret' => session('two_factor_secret'),
                'provisioning_uri' => session('two_factor_uri'),
                'recovery_codes' => session('two_factor_recovery_codes') ?: $currentUser->two_factor_recovery_codes,
            ],
        ]);
    }

    /**
     * Generate new Base32 TOTP secret for current staff member.
     */
    public function setup2fa(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $secret = User::generateBase32Secret(16);

        $user->two_factor_secret = $secret;
        $user->save();

        $issuer = 'PK-POS Platform';
        $provisioningUri = "otpauth://totp/{$issuer}:{$user->email}?secret={$secret}&issuer=" . urlencode($issuer);

        return back()
            ->with('two_factor_secret', $secret)
            ->with('two_factor_uri', $provisioningUri)
            ->with('success', 'Two-factor secret key generated. Scan QR code or enter secret into Authenticator app.');
    }

    /**
     * Confirm submitted 6-digit TOTP code and enable 2FA.
     */
    public function confirm2fa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->verifyTwoFactorCode($validated['code'])) {
            return back()->withErrors(['code' => 'The provided 6-digit authentication code is invalid or expired.']);
        }

        $recoveryCodes = User::generateRecoveryCodes(8);

        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->save();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => $user->id,
            'event' => 'two_factor_authentication_enabled',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => ['two_factor_confirmed_at' => $user->two_factor_confirmed_at->toIso8601String()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()
            ->with('two_factor_recovery_codes', $recoveryCodes)
            ->with('success', 'Two-Factor Authentication (2FA) is now ACTIVATED! Please safely store your emergency recovery codes.');
    }

    /**
     * Disable 2FA.
     */
    public function disable2fa(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => $user->id,
            'event' => 'two_factor_authentication_disabled',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => ['action' => 'disabled'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Two-Factor Authentication (2FA) has been disabled.');
    }

    /**
     * Terminate an individual session.
     */
    public function killSession(Request $request, string $sessionId): RedirectResponse
    {
        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if ($session) {
            AuditLog::create([
                'tenant_id' => null,
                'user_id' => Auth::id(),
                'event' => 'user_session_terminated',
                'auditable_type' => User::class,
                'auditable_id' => $session->user_id ?: Auth::id(),
                'new_values' => [
                    'session_id' => $sessionId,
                    'ip_address' => $session->ip_address,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::table('sessions')->where('id', $sessionId)->delete();
        }

        return back()->with('success', 'Target session was terminated immediately.');
    }

    /**
     * Platform-wide Emergency Killswitch: Terminates all active sessions across Pakistan.
     */
    public function killAllSessions(Request $request): RedirectResponse
    {
        $currentSessionId = session()->getId();

        $revokedCount = DB::table('sessions')
            ->where('id', '!=', $currentSessionId)
            ->delete();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'platform_emergency_killswitch_executed',
            'auditable_type' => User::class,
            'auditable_id' => Auth::id(),
            'new_values' => [
                'revoked_sessions_count' => $revokedCount,
                'preserved_session_id' => $currentSessionId,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "PLATFORM KILLSWITCH EXECUTED: {$revokedCount} active session(s) revoked immediately across the fleet.");
    }

    /**
     * Toggle Platform-wide Mandatory 2FA Policy for Staff.
     */
    public function toggleEnforceStaff2fa(Request $request): RedirectResponse
    {
        $currentEnforced = User::where('super_admin', true)->where('two_factor_enforced', true)->exists();
        $newState = ! $currentEnforced;

        User::where('super_admin', true)->update(['two_factor_enforced' => $newState]);

        $statusStr = $newState ? 'ENFORCED (Mandatory)' : 'OPTIONAL';

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'platform_mandatory_2fa_policy_toggled',
            'auditable_type' => User::class,
            'auditable_id' => Auth::id(),
            'new_values' => ['mandatory_staff_2fa' => $newState],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Platform 2FA policy is now {$statusStr} for all staff accounts.");
    }

    /**
     * Helper to parse user agent string to human readable browser & OS.
     */
    protected function parseBrowser(?string $ua): string
    {
        if (! $ua) {
            return 'Unknown Device';
        }

        $browser = 'Browser';
        if (str_contains($ua, 'Edg')) $browser = 'Microsoft Edge';
        elseif (str_contains($ua, 'Chrome')) $browser = 'Google Chrome';
        elseif (str_contains($ua, 'Firefox')) $browser = 'Mozilla Firefox';
        elseif (str_contains($ua, 'Safari')) $browser = 'Apple Safari';

        $os = 'Device';
        if (str_contains($ua, 'Windows')) $os = 'Windows';
        elseif (str_contains($ua, 'Macintosh')) $os = 'macOS';
        elseif (str_contains($ua, 'Linux')) $os = 'Linux';
        elseif (str_contains($ua, 'Android')) $os = 'Android';
        elseif (str_contains($ua, 'iPhone')) $os = 'iOS';

        return "{$browser} on {$os}";
    }
}

