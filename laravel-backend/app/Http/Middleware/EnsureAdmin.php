<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminAccess;
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

        if (! $user instanceof User || ! AdminAccess::allows($user)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Forbidden.', null, 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
