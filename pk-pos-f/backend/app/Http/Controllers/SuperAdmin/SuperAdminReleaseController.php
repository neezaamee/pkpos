<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosTerminal;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminReleaseController extends Controller
{
    /**
     * Display the releases, changelogs, and OTA update packages.
     */
    public function index(Request $request): Response
    {
        $platform = $request->input('target_platform');
        $channel = $request->input('release_channel');
        $search = $request->input('search');

        $query = Release::query()->latest('published_at');

        if ($platform) {
            $query->where('target_platform', $platform);
        }

        if ($channel) {
            $query->where('release_channel', $channel);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('version', 'ILIKE', "%{$search}%")
                    ->orWhere('title', 'ILIKE', "%{$search}%")
                    ->orWhere('summary', 'ILIKE', "%{$search}%");
            });
        }

        $releases = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'version' => $r->version,
                'release_channel' => $r->release_channel,
                'target_platform' => $r->target_platform,
                'tauri_target' => $r->tauri_target,
                'title' => $r->title,
                'summary' => $r->summary,
                'changelog_markdown' => $r->changelog_markdown,
                'download_url' => $r->download_url,
                'signature' => $r->signature ? substr($r->signature, 0, 20) . '...' : null,
                'sha256_checksum' => $r->sha256_checksum,
                'min_supported_version' => $r->min_supported_version,
                'is_critical' => (bool) $r->is_critical,
                'is_published' => (bool) $r->is_published,
                'published_at' => $r->published_at?->format('Y-m-d H:i') ?? 'Unpublished',
            ]);

        // Calculate latest releases and fleet adoption metrics
        $latestDesktop = Release::where('target_platform', 'desktop_windows')
            ->where('is_published', true)
            ->latest('published_at')
            ->first();

        $latestVersion = $latestDesktop?->version ?? '0.1.0';

        $outdatedTerminalsCount = 0;
        if ($latestDesktop) {
            $terminals = PosTerminal::withoutGlobalScopes()
                ->where('is_active', true)
                ->whereNotNull('app_version')
                ->get(['app_version']);

            foreach ($terminals as $term) {
                if (version_compare(ltrim($term->app_version, 'v'), ltrim($latestVersion, 'v'), '<')) {
                    $outdatedTerminalsCount++;
                }
            }
        }

        $metrics = [
            'total_releases' => Release::count(),
            'latest_desktop_version' => $latestVersion,
            'outdated_terminals_count' => $outdatedTerminalsCount,
            'min_supported_version' => $latestDesktop?->min_supported_version ?? '0.1.0',
        ];

        return Inertia::render('SuperAdmin/Releases/Index', [
            'releases' => $releases,
            'metrics' => $metrics,
            'filters' => [
                'target_platform' => $platform,
                'release_channel' => $channel,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created release.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:30'],
            'release_channel' => ['required', 'string', 'in:stable,beta,nightly'],
            'target_platform' => ['required', 'string', 'in:desktop_windows,desktop_linux,desktop_macos,cloud_backend'],
            'tauri_target' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'changelog_markdown' => ['nullable', 'string'],
            'download_url' => ['nullable', 'url', 'max:1000'],
            'signature' => ['nullable', 'string'],
            'sha256_checksum' => ['nullable', 'string', 'max:64'],
            'min_supported_version' => ['nullable', 'string', 'max:30'],
            'is_critical' => ['boolean'],
            'is_published' => ['boolean'],
        ]);

        if (! empty($validated['is_published'])) {
            $validated['published_at'] = now();
        }

        $release = Release::create($validated);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'release_created',
            'auditable_type' => Release::class,
            'auditable_id' => $release->id,
            'new_values' => [
                'version' => $release->version,
                'platform' => $release->target_platform,
                'title' => $release->title,
                'is_critical' => $release->is_critical,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Release 'v{$release->version}' created successfully.");
    }

    /**
     * Update an existing release.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $release = Release::findOrFail($id);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:30'],
            'release_channel' => ['required', 'string', 'in:stable,beta,nightly'],
            'target_platform' => ['required', 'string', 'in:desktop_windows,desktop_linux,desktop_macos,cloud_backend'],
            'tauri_target' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'changelog_markdown' => ['nullable', 'string'],
            'download_url' => ['nullable', 'url', 'max:1000'],
            'signature' => ['nullable', 'string'],
            'sha256_checksum' => ['nullable', 'string', 'max:64'],
            'min_supported_version' => ['nullable', 'string', 'max:30'],
            'is_critical' => ['boolean'],
            'is_published' => ['boolean'],
        ]);

        if (! empty($validated['is_published']) && ! $release->is_published) {
            $validated['published_at'] = now();
        }

        $release->update($validated);

        return back()->with('success', "Release 'v{$release->version}' updated successfully.");
    }

    /**
     * Toggle the published status of a release.
     */
    public function togglePublish(Request $request, string $id): RedirectResponse
    {
        $release = Release::findOrFail($id);
        $newStatus = ! $release->is_published;

        $release->update([
            'is_published' => $newStatus,
            'published_at' => $newStatus ? ($release->published_at ?? now()) : null,
        ]);

        $statusLabel = $newStatus ? 'published' : 'unpublished';

        return back()->with('success', "Release 'v{$release->version}' has been {$statusLabel}.");
    }

    /**
     * Delete a release record.
     */
    public function destroy(string $id): RedirectResponse
    {
        $release = Release::findOrFail($id);
        $v = $release->version;
        $release->delete();

        return back()->with('success', "Release 'v{$v}' deleted successfully.");
    }
}

