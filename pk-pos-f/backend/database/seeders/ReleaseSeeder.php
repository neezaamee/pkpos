<?php

namespace Database\Seeders;

use App\Models\Release;
use Illuminate\Database\Seeder;

class ReleaseSeeder extends Seeder
{
    /**
     * Run the release and changelog seeder.
     */
    public function run(): void
    {
        $releases = [
            [
                'version' => '0.1.0',
                'release_channel' => 'stable',
                'target_platform' => 'desktop_windows',
                'tauri_target' => 'windows-x86_64',
                'title' => 'PK-POS Initial Desktop Alpha',
                'summary' => 'Initial desktop POS client release with local SQLite offline engine and basic cash transactions.',
                'changelog_markdown' => "### Features\n- Local SQLite engine with offline transaction queuing.\n- High-speed thermal receipt printing support.\n- FBR basic fiscalization integration.",
                'download_url' => 'https://releases.pkpos.pk/downloads/pk-pos-setup-0.1.0.msi',
                'signature' => 'dW50cnVzdGVkIGNvbW1lbnQ6IG1pbmlzaWduIHB1YmxpYyBrZXk=...',
                'sha256_checksum' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'min_supported_version' => '0.1.0',
                'is_critical' => false,
                'is_published' => true,
                'published_at' => now()->subMonths(1),
            ],
            [
                'version' => '0.2.0',
                'release_channel' => 'stable',
                'target_platform' => 'desktop_windows',
                'tauri_target' => 'windows-x86_64',
                'title' => 'FBR SRO Compliance & Provincial Tax Schedules',
                'summary' => 'Major update bringing automated PRA, SRB, KPRA tax calculations and desktop OTA update notifications.',
                'changelog_markdown' => "### Features\n- Automated PRA Punjab & SRB Sindh restaurant tax incentives (5% PRA, 8% SRB for digital payments).\n- Real-time FBR IRIS gateway heartbeat indicator.\n- Hardware pairing and remote terminal revocation.\n\n### Bug Fixes\n- Resolved offline sales synchronization race conditions.\n- Fixed 24-hour statutory deadline timestamp calculations.",
                'download_url' => 'https://releases.pkpos.pk/downloads/pk-pos-setup-0.2.0.msi',
                'signature' => 'dW50cnVzdGVkIGNvbW1lbnQ6IG1pbmlzaWduIHB1YmxpYyBrZXk=...',
                'sha256_checksum' => 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
                'min_supported_version' => '0.1.0',
                'is_critical' => true,
                'is_published' => true,
                'published_at' => now()->subDays(2),
            ],
        ];

        foreach ($releases as $rel) {
            Release::firstOrCreate(['version' => $rel['version'], 'target_platform' => $rel['target_platform']], $rel);
        }
    }
}

