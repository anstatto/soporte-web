<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, $role): Response
    {
        if (!Auth::check() || !Auth::user()->roles->contains('name', $role)) {
            return response()->view('errors.403', [], 403); // Redirige a la vista 403 si no tiene el rol
        }

        return $next($request);
    }
}
