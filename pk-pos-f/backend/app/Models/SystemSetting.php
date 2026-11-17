<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'is_secret',
        'label',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    /**
     * Retrieve a system setting value by key with optional type casting and decryption.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting || $setting->value === null) {
            return $default;
        }

        $raw = $setting->value;

        // Decrypt if secret
        if ($setting->is_secret && ! empty($raw)) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                Log::warning("SystemSetting: Failed to decrypt setting key '{$key}': " . $e->getMessage());
                return $default;
            }
        }

        // Type casting
        return match ($setting->type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            'json', 'array' => json_decode($raw, true) ?? $default,
            default => $raw,
        };
    }

    /**
     * Store or update a system setting value with automatic encryption for secrets.
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param string $type
     * @param bool $isSecret
     * @param string|null $label
     * @param string|null $description
     * @return static
     */
    public static function set(
        string $key,
        mixed $value,
        string $group = 'platform_defaults',
        string $type = 'string',
        bool $isSecret = false,
        ?string $label = null,
        ?string $description = null
    ): static {
        $storedValue = $value;

        if (is_array($value) || is_object($value)) {
            $storedValue = json_encode($value);
            $type = 'json';
        } elseif (is_bool($value)) {
            $storedValue = $value ? '1' : '0';
            $type = 'boolean';
        } elseif ($value !== null) {
            $storedValue = (string) $value;
        }

        if ($isSecret && ! empty($storedValue)) {
            $storedValue = Crypt::encryptString($storedValue);
        }

        $payload = [
            'value' => $storedValue,
            'group' => $group,
            'type' => $type,
            'is_secret' => $isSecret,
        ];

        if ($label !== null) {
            $payload['label'] = $label;
        }
        if ($description !== null) {
            $payload['description'] = $description;
        }

        return static::updateOrCreate(['key' => $key], $payload);
    }

    /**
     * Get safe masked representation of secret values for frontend presentation.
     *
     * @return string
     */
    public function getMaskedValue(): string
    {
        if (! $this->is_secret) {
            return (string) ($this->value ?? '');
        }

        if (empty($this->value)) {
            return '';
        }

        try {
            $decrypted = Crypt::decryptString($this->value);
            $len = strlen($decrypted);
            if ($len <= 8) {
                return str_repeat('•', 8);
            }

            $prefix = substr($decrypted, 0, 4);
            $suffix = substr($decrypted, -4);
            return "{$prefix}••••••••{$suffix}";
        } catch (\Throwable) {
            return '••••••••';
        }
    }
}
