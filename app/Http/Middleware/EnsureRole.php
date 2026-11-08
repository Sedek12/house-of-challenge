<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Vérification de rôle côté serveur : role:ADMIN  ou  role:ADMIN,INTERVIEWER */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            abort(403, 'Accès refusé.');
        }
        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Accès refusé.');
        }

        return $next($request);
    }
}
