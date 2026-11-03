<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    /**
     * Boot the LogsActivity trait for a model.
     */
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            static::recordAuditActivity($model, 'created', null, static::filterSensitiveAttributes($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            $ignored = array_merge(['updated_at'], $model->getHidden(), static::getAuditIgnoredAttributes());
            $relevantChanges = Arr::except($changes, $ignored);

            if (empty($relevantChanges)) {
                return;
            }

            $original = Arr::only($model->getRawOriginal(), array_keys($relevantChanges));

            static::recordAuditActivity(
                $model,
                'updated',
                static::filterSensitiveAttributes($model, $original),
                static::filterSensitiveAttributes($model, $relevantChanges)
            );
        });

        static::deleted(function (Model $model) {
            static::recordAuditActivity($model, 'deleted', static::filterSensitiveAttributes($model, $model->getAttributes()), null);
        });
    }

    /**
     * Attributes that should always be ignored from audit logging.
     *
     * @return array<int, string>
     */
    protected static function getAuditIgnoredAttributes(): array
    {
        return [
            'password',
            'remember_token',
            'bearer_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];
    }

    /**
     * Strip out sensitive and hidden fields from the logged payload.
     *
     * @param Model $model
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected static function filterSensitiveAttributes(Model $model, array $attributes): array
    {
        $ignored = array_merge($model->getHidden(), static::getAuditIgnoredAttributes());

        return Arr::except($attributes, $ignored);
    }

    /**
     * Resolve the target tenant id for this model mutation.
     *
     * @param Model $model
     * @return string|null
     */
    protected static function resolveTenantId(Model $model): ?string
    {
        if ($model instanceof Tenant) {
            return $model->id;
        }

        if (isset($model->tenant_id)) {
            return $model->tenant_id;
        }

        if (Auth::hasUser() && Auth::user()?->tenant_id) {
            return Auth::user()->tenant_id;
        }

        return null;
    }

    /**
     * Record an audit log entry in the database.
     *
     * @param Model $model
     * @param string $event
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    protected static function recordAuditActivity(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLog::create([
                'tenant_id' => static::resolveTenantId($model),
                'user_id' => Auth::id(),
                'event' => $event,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to record audit log for {$event} on " . get_class($model) . ": " . $e->getMessage());
        }
    }
}

