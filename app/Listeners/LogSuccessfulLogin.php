<?php

namespace App\Listeners;

use App\Models\UserActivity;
use Illuminate\Auth\Events\Login;
// use Illuminate\Events\Attribute\AsListener;
use Illuminate\Support\Facades\Log;

// #[AsListener]
class LogSuccessfulLogin
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
    public function handle(Login $event): void
    {


        try {
            Log::info('LogSuccessfulLogin listener triggered', ['user_id' => $event->user->id]);
            UserActivity::create([
                'user_id' => $event->user->id,
                'action' => 'Login',
                'description' => 'User login',
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save login activity', ['message' => $e->getMessage()]);
        }

        
    }
}
