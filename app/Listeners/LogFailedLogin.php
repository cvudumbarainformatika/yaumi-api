<?php

namespace App\Listeners;

use App\Models\UserActivity;
use Illuminate\Auth\Events\Failed;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;

class LogFailedLogin
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
    public function handle(Failed $event): void
    {
        UserActivity::create([
            'user_id' => $event->user->id ?? null,
            'action' => 'failed_login',
            'description' => 'Login gagal dengan email: ' . request('email'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);
    }
}
