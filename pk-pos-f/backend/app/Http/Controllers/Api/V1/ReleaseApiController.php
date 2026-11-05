<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReleaseApiController extends Controller
{
    /**
     * Official Tauri v2 Over-The-Air (OTA) auto-updater manifest endpoint.
     * Follows Tauri updater standard: returns 204 No Content if client is up to date,
     * or 200 JSON with update payload and signature if a newer release exists.
     */
    public function tauriUpdate(Request $request, string $target, string $current_version): Response|JsonResponse
    {
        $cleanTarget = strtolower($target);

        $query = Release::where('is_published', true)
            ->where('release_channel', 'stable')
            ->latest('published_at');

        if (str_contains($cleanTarget, 'windows')) {
            $query->where('target_platform', 'desktop_windows');
        } elseif (str_contains($cleanTarget, 'linux')) {
            $query->where('target_platform', 'desktop_linux');
        } elseif (str_contains($cleanTarget, 'darwin') || str_contains($cleanTarget, 'macos')) {
            $query->where('target_platform', 'desktop_macos');
        }

        $latestRelease = $query->first();

        if (! $latestRelease) {
            return response()->noContent();
        }

        $cleanCurrent = ltrim($current_version, 'vV');
        $cleanLatest = ltrim($latestRelease->version, 'vV');

        // If client is already on current or newer version, return 204
        if (version_compare($cleanLatest, $cleanCurrent, '<=')) {
            return response()->noContent();
        }

        // Return Tauri v2 update manifest
        $pubDate = $latestRelease->published_at?->toIso8601String() ?? now()->toIso8601String();
        $notes = $latestRelease->changelog_markdown ?: ($latestRelease->summary ?: "Update to version {$latestRelease->version}");

        return response()->json([
            'version' => $cleanLatest,
            'notes' => $notes,
            'pub_date' => $pubDate,
            'signature' => $latestRelease->signature ?? '',
            'url' => $latestRelease->download_url ?? '',
            'platforms' => [
                $target => [
                    'signature' => $latestRelease->signature ?? '',
                    'url' => $latestRelease->download_url ?? '',
                ],
            ],
        ]);
    }

    /**
     * Public changelog feed for merchant web portal and POS client "What's New" modal.
     */
    public function changelog(Request $request): JsonResponse
    {
        $platform = $request->input('target_platform', 'desktop_windows');

        $releases = Release::where('is_published', true)
            ->when($platform, fn ($q) => $q->where('target_platform', $platform))
            ->latest('published_at')
            ->take(10)
            ->get([
                'id',
                'version',
                'title',
                'summary',
                'changelog_markdown',
                'download_url',
                'is_critical',
                'published_at',
            ]);

        return response()->json([
            'releases' => $releases,
        ]);
    }

    /**
     * Quick client version status check and minimum version enforcement probe.
     */
    public function versionCheck(Request $request): JsonResponse
    {
        $clientVersion = $request->input('current_version') ?? $request->header('X-Client-Version') ?? '0.1.0';
        $cleanCurrent = ltrim($clientVersion, 'vV');

        $latest = Release::where('target_platform', 'desktop_windows')
            ->where('is_published', true)
            ->latest('published_at')
            ->first();

        if (! $latest) {
            return response()->json([
                'status' => 'current',
                'is_outdated' => false,
                'is_deprecated' => false,
            ]);
        }

        $cleanLatest = ltrim($latest->version, 'vV');
        $isOutdated = version_compare($cleanLatest, $cleanCurrent, '>');
        $isDeprecated = $latest->isVersionDeprecated($cleanCurrent);

        return response()->json([
            'current_version' => $cleanCurrent,
            'latest_version' => $cleanLatest,
            'is_outdated' => $isOutdated,
            'is_deprecated' => $isDeprecated,
            'is_critical' => (bool) $latest->is_critical,
            'min_supported_version' => $latest->min_supported_version,
            'download_url' => $latest->download_url,
            'release_title' => $latest->title,
        ]);
    }

    /**
     * Redirect to the latest published Windows desktop client installer.
     */
    public function downloadLatestDesktop(Request $request)
    {
        $latest = Release::where('target_platform', 'desktop_windows')
            ->where('is_published', true)
            ->latest('published_at')
            ->first();

        if (! $latest || ! $latest->download_url) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No published desktop release is currently available.',
                ], 404);
            }

            return redirect()->route('dashboard')->with('error', 'No desktop POS client installer is currently published. Contact your administrator.');
        }

        return redirect()->away($latest->download_url);
    }
}

