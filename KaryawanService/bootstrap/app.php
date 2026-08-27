<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', \App\Http\Middleware\AuthenticateAccessMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tanpa handler ini, error yang tidak tertangkap dirender Laravel apa
        // adanya: bentuknya BUKAN envelope resCode/resMsg sehingga klien tidak
        // bisa mem-parse, dan saat APP_DEBUG=true ikut memuat nama exception,
        // path absolut file, serta stack trace. Gateway meneruskan body service
        // apa adanya, jadi semuanya sampai ke klien.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Validasi punya bentuknya sendiri (422 + daftar error per field);
            // biarkan Laravel yang menanganinya.
            if ($e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $msg    = $e->getMessage();

            // 5xx bisa membawa apa saja — pernah terjadi SQL utuh beserta hash
            // password. Detailnya ke log, klien cukup dapat kode rujukan.
            if ($status >= 500) {
                $ref = strtoupper(bin2hex(random_bytes(4)));
                Log::error("[{$ref}] " . $msg);
                $msg = "Terjadi kesalahan di server. Sertakan kode {$ref} saat melaporkannya.";
            }

            return response()->json([
                'resCode'   => $status,
                'resPhrase' => Response::$statusTexts[$status] ?? 'Error',
                'resStatus' => 'fail',
                'resMsg'    => $msg,
                'data'      => [],
            ], $status);
        });
    })->create();
