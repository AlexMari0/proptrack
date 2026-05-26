<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the trait and register Eloquent event listeners.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('create', null, $model->getAuditAttributes($model));
        });

        static::updated(function (Model $model) {
            // Get changes
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }

            // Exclude common standard timestamp columns or hidden fields
            $exclude = ['updated_at', 'remember_token', 'password'];
            
            $old = [];
            $new = [];
            
            foreach ($changes as $key => $newValue) {
                if (in_array($key, $exclude)) {
                    continue;
                }
                $oldValue = $model->getOriginal($key);
                // Only log if they are actually different
                if ($oldValue !== $newValue) {
                    $old[$key] = $oldValue;
                    $new[$key] = $newValue;
                }
            }

            if (!empty($new)) {
                $model->logAudit('update', $old, $new);
            }
        });

        static::deleted(function (Model $model) {
            $model->logAudit('delete', $model->getAuditAttributes($model), null);
        });
    }

    /**
     * Create an audit log entry for the current model action.
     */
    protected function logAudit(string $action, ?array $old, ?array $new): void
    {
        $userId = auth()->id();
        $ip = request() ? request()->ip() : null;

        AuditLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'model_type' => get_class($this),
            'model_id'   => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }

    /**
     * Get key attributes for logging when model is created or deleted.
     */
    protected function getAuditAttributes(Model $model): array
    {
        $attributes = $model->toArray();
        
        // Filter out sensitive data
        $exclude = ['password', 'remember_token', 'updated_at', 'created_at'];
        return array_filter($attributes, function ($key) use ($exclude) {
            return !in_array($key, $exclude);
        }, ARRAY_FILTER_USE_KEY);
    }
}
