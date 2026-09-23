<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTusdHook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('tus.hooks_secret');
        $user = (string) config('tus.hooks_user');

        $providedSecret = (string) ($request->getPassword() ?: $request->header('X-Tusd-Secret', ''));
        $providedUser = (string) ($request->getUser() ?: $user);

        if ($secret === '' || $providedUser !== $user || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
