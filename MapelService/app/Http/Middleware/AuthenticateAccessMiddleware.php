<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AuthenticateAccessMiddleware
{
    use ApiResponser;
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
		$xffHeader = $request->headers->get('X-Forwarded-For', '');

		// Pecah berdasarkan koma, lalu buang spasi di tiap elemen
		$ipList = array_filter(
			array_map('trim', explode(',', $xffHeader)),
			fn($ip) => !empty($ip)
		);

		// client IP → elemen pertama
		$clientIp  = $ipList[0] ?? $request->ip();
		// gateway IP → elemen terakhir
		$gatewayIp = end($ipList) ?? $request->ip();

        Log::info('Incoming request', [
			'all_ips' => $ipList,
			'gateway_ip' => $gatewayIp,
			'client_ip' => $clientIp,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

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
