<?php

namespace App\Traits;

use App\Models\UserActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            self::log('created', $model);
        });

        static::updated(function ($model) {
            self::log('updated', $model);
        });

        static::deleted(function ($model) {
            self::log('deleted', $model);
        });
    }

    protected static function log($event, $model)
    {
        
        // Hanya log saat bukan di console (misal queue, seeder, dll bisa skip)
        if (app()->runningInConsole()) {
            return;
        }


        try {
            $before = null;
            $after  = null;

            if ($event === 'created') {
                $after = $model->getAttributes();
            } elseif ($event === 'updated') {
                $before = array_intersect_key($model->getOriginal(), $model->getChanges());
                $after  = $model->getChanges();
            } elseif ($event === 'deleted') {
                $before = $model->getOriginal();
            }

            UserActivity::create([
                'user_id'     => Auth::id(),
                'action'      => class_basename($model) . ' ' . $event,
                'description' => json_encode([
                    'before' => $before,
                    'after'  => $after,
                ], JSON_UNESCAPED_UNICODE),
                'ip_address'  => request()?->ip(),
                'user_agent'  => request()?->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log user activity', [
                'event' => $event,
                'model' => class_basename($model),
                'message' => $e->getMessage(),
            ]);
        }

    }
}
