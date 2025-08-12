<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ApiResponser;
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, $roles)) {
            return $this->response(
                "Anda tidak memiliki akses.", 
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
