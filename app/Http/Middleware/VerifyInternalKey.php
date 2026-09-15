<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalKey
{
    /**
     * API ini hanya untuk dipanggil server frontend (illustrasia2), bukan browser atau pihak luar.
     * Frontend yang memvalidasi upload file, login Google admin, dan rate limit per pengunjung;
     * tanpa kunci ini siapa pun bisa melewati semua itu dengan memanggil API langsung.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('app.internal_api_key');

        if ($key === '' || !hash_equals($key, (string) $request->header('X-Internal-Key'))) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
