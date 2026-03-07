<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', null, 401);
        }

        $user->loadMissing('roles');

        $isAdmin = (string) ($user->role ?? '') === 'admin'
            || $user->roles->contains(static fn ($role): bool => (string) $role->code === 'admin');

        if (! $isAdmin) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        return $next($request);
    }
}
