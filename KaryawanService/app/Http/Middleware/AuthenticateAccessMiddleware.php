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
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        // Replay protection: tolak request lebih dari 5 menit
        if (!$timestamp || abs(time() - (int)$timestamp) > 300) {
            return $this->response("Request expired.", Response::HTTP_UNAUTHORIZED);
        }

        $contentType = $request->header('Content-Type', '');
        $isGetOrDelete = in_array($request->method(), ['GET', 'DELETE']);
        if ($isGetOrDelete) {
            $body = http_build_query($request->query->all());
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            $body = '';
        } else {
            $body = $request->getContent();
        }
        $secrets = array_map('trim', explode(',', config('services.accepted_secrets', '')));
        
        $valid = false;
        foreach ($secrets as $secret) {
            if (!empty($secret) && hash_equals(
                hash_hmac('sha256', $timestamp . $body, $secret),
                (string) $signature
            )) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return $this->response("Anda tidak memiliki akses.", Response::HTTP_UNAUTHORIZED);
        }

        // Log setelah auth sukses
        $ipList = array_filter(array_map('trim', explode(',', $request->headers->get('X-Forwarded-For', ''))));
        Log::info('Authenticated request', [
            'client_ip'  => $ipList[0] ?? $request->ip(),
            'gateway_ip' => end($ipList) ?: $request->ip(),
            'path'   => $request->path(),
            'method' => $request->method(),
        ]);

        return $next($request);
    }
}
