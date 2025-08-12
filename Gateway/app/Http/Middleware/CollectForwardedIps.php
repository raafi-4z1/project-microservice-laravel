<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CollectForwardedIps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $originalIps = $request->getClientIps();
        if (empty($originalIps)) {
            $originalIps = [$request->ip()];
        }
		
        Log::info('Incoming request', [
			'ip' => $originalIps,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        $gatewayIp = $request->server('SERVER_ADDR')
                ?? gethostbyname(gethostname());
        $allIps = array_unique(array_merge($originalIps, [$gatewayIp]));

        $xff = implode(', ', $allIps);
        $request->headers->set('X-Forwarded-For', $xff);
        return $next($request);
    }
}
