<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (! in_array($user->rol, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos suficientes para realizar esta acción. Se requiere uno de los siguientes roles: ' . implode(', ', $roles) . '.',
            ], 403);
        }

        return $next($request);
    }
}
