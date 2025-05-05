<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Hanya untuk request POST/PUT/PATCH yang mengubah data
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }
        
        // Buat ID unik berdasarkan user, route, dan request data
        $requestId = md5(
            $request->user()?->id . 
            $request->route()->uri() . 
            json_encode($request->all())
        );
        
        // Cek apakah request ini sudah diproses dalam 10 detik terakhir
        if (Cache::has('request_' . $requestId)) {
            return response()->json([
                'message' => 'Permintaan duplikat terdeteksi. Silakan tunggu beberapa saat.'
            ], 429);
        }
        
        // Simpan request ID di cache selama 10 detik
        Cache::put('request_' . $requestId, true, 10);
        
        return $next($request);
    }
}