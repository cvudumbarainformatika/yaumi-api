<?php

namespace App\Listeners;

use App\Models\UserActivity;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogLogout
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        try {
            Log::info('LogLogout listener triggered', ['user_id' => $event->user->id]);

            UserActivity::create([
                'user_id' => $event->user->id,
                'action' => 'Logout',
                'description' => 'User Logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save logout activity', ['message' => $e->getMessage()]);
        }
    }
}
