<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! Access::allows($request->user(), $ability)) {
            return response()->json(['message' => 'Votre rôle ne permet pas cette action.'], 403);
        }

        return $next($request);
    }
}
