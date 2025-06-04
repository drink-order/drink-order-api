<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function redirectTo($request)
    {
        // Return JSON error instead of redirecting
        if (! $request->expectsJson()) {
            abort(response()->json(['message' => 'Unauthenticated.'], 401));
        }
    }
}
