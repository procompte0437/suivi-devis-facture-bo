<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! Access::isClient($user) || ! $user->client_id) {
            return response()->json(['message' => 'Cet espace est réservé aux clients.'], 403);
        }

        return $next($request);
    }
}
