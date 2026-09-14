<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'admin_only'], 403);
            }

            abort(403, 'Only an admin can open this page.');
        }

        return $next($request);
    }
}
