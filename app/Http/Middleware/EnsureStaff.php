<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloque les comptes clients hors de leur espace. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Access::isClient($request->user())) {
            return response()->json(['message' => 'Cet écran est réservé à l’équipe.'], 403);
        }

        return $next($request);
    }
}
