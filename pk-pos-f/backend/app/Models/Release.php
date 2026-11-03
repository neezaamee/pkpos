<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'releases';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'release_channel',
        'target_platform',
        'tauri_target',
        'title',
        'summary',
        'changelog_markdown',
        'download_url',
        'signature',
        'sha256_checksum',
        'min_supported_version',
        'is_critical',
        'is_published',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Check if this release version is strictly newer than a given version string.
     */
    public function isNewerThan(string $currentVersion): bool
    {
        $v1 = ltrim($this->version, 'vV');
        $v2 = ltrim($currentVersion, 'vV');

        return version_compare($v1, $v2, '>');
    }

    /**
     * Check if a given client version is below the minimum supported version for this release.
     */
    public function isVersionDeprecated(string $clientVersion): bool
    {
        if (empty($this->min_supported_version)) {
            return false;
        }

        $min = ltrim($this->min_supported_version, 'vV');
        $current = ltrim($clientVersion, 'vV');

        return version_compare($current, $min, '<');
    }
}

