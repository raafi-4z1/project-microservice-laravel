<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAccessMiddleware
{
    use ApiResponser;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validSecrets = explode(',', env('ACCEPTED_SECRETS'));
        if(in_array($request->header('Authorization'), $validSecrets))
        {
            return $next($request);
        }

        // abort(Response::HTTP_UNAUTHORIZED);
        return $this->response(
                "Anda tidak memiliki akses.", 
                Response::HTTP_UNAUTHORIZED
            );

        //This for testing purpose and should be reomoved in production
        // return $next($request);
    }
}
