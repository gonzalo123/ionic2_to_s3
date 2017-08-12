<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->get('token');
        if ($token === getenv('token')) {
            return response('Admin Login', 401);
        }

        return $next($request);
    }
}
