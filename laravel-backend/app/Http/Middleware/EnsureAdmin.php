<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        if (! $user instanceof User || ! $this->isAdmin($user)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        return $next($request);
    }

    private function isAdmin(User $user): bool
    {
        $user->loadMissing('roles:id,code');

        $hasAdminRole = $user->role === 'admin'
            || $user->roles->contains(static fn ($role): bool => $role->code === 'admin');

        if (! $hasAdminRole) {
            return false;
        }

        if ($user->currentAccessToken() === null) {
            return true;
        }

        return $user->tokenCan('admin');
    }
}
