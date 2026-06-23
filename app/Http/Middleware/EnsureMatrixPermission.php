<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMatrixPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role, int $minLevel = 0): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // 1. Check Role (admin bypasses role check)
        if ($role !== 'any' && $user->role !== $role && $user->role !== 'admin') {
            abort(403, "Unauthorized: Requires role {$role}");
        }

        // 2. Check Level
        if ($user->level < $minLevel && $user->role !== 'admin') {
            abort(403, "Unauthorized: Requires level {$minLevel}");
        }

        return $next($request);
    }
}
