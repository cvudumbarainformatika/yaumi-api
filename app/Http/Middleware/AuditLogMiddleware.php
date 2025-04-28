<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Log user activity
        if (Auth::check()) {
            $user = Auth::user();
            $activity = [
                'user_id' => $user->id,
                'activity' => $request->method() . ' ' . $request->path(),
                'timestamp' => now(),
                'details' => json_encode($request->all())
            ];

            Log::channel('audit')->info('User Activity', $activity);
        }

        return $response;
    }
}
