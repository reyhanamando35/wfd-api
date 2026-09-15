<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Only let through Sanctum tokens issued by /admin/check-email for an email still in the admins table.
     * Checking the token name too stops a customer who registered with an admin's email from getting in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user();

        if (!$user
            || $user->currentAccessToken()?->name !== 'auth_token_admin'
            || !Admin::where('email', $user->email)->exists()) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 401);
        }

        // So $request->user() in admin controllers resolves the token user (same as auth:sanctum did)
        auth()->shouldUse('sanctum');

        return $next($request);
    }
}
