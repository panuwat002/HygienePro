<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('create', null, $model->attributesToArray());
        });

        static::updated(function ($model) {
            $model->logActivity('update', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logActivity('delete', $model->attributesToArray(), null);
        });
    }

    public function logActivity($action, $oldValues = null, $newValues = null, $description = null)
    {
        // Filter out hidden attributes if needed
        $oldValues = $oldValues ? $this->filterHidden($oldValues) : null;
        $newValues = $newValues ? $this->filterHidden($newValues) : null;

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description ?? ucfirst($action) . ' ' . class_basename($this),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    protected function filterHidden($attributes)
    {
        if (!is_array($attributes)) {
            return $attributes;
        }
        return array_diff_key($attributes, array_flip($this->getHidden()));
    }
}
