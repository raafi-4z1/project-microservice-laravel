<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //"alias(string $name, string $class)" membuat kita bisa memanggilnya di route seperti "->middleware('check.role:SuperAdmin,Admin')".
        $middleware->alias([
            'check.role' => \App\Http\Middleware\CheckRole::class,
            // Akun dengan password default (auto-created) diblokir dari semua
            // endpoint kecuali /password, /user, /logout sampai ganti password.
            // Dipasang per-group SETELAH auth:api (global tidak bekerja —
            // guard belum aktif saat middleware global berjalan)
            'force.pwd'  => \App\Http\Middleware\ForcePasswordChange::class,
            // Autentikasi terminal absensi (scan). Dipakai di route scan (#5).
            'auth.terminal' => \App\Http\Middleware\AuthenticateTerminal::class,
        ]);
        $middleware->append(\App\Http\Middleware\CollectForwardedIps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 🚦 Rate limit
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'resCode'   => 429,
                'resPhrase' => 'Too Many Requests',
                'resStatus' => 'fail',
                'resMsg'    => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.',
                'data'      => []
            ], 429);
        });

        // 📦 Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            // if ($request->is('api/*')) {
                return response()->json([
                    'resCode' => 404,
                    'resPhrase' => "Not Found",
                    'resStatus' => "fail",
                    'resMsg' => "Record not found.",
                    'data' => $e->getMessage()
                ], 404);
            // }
        });

        // 🚫 Fallback untuk semua API exception
        $exceptions->render(function (Throwable $e, Request $request) {
            // if ($request->is('api/*')) {
                $msg = "Unexpected Error.";
                $err = 500;

                if (str_contains($e->getMessage(), '[login]')) {
                    $msg = "Authentication failed or token expired.";
                    $err = 401;
                } elseif (str_contains($e->getMessage(), 'method is not')) {
                    $msg = "Method not Allowed.";
                    $err = 405;
                } elseif (str_contains($e->getMessage(), 'Unauthenticated')) {
                    $msg = "Unauthenticated";
                    $err = 401;
                }

                // `data` dulu selalu berisi pesan exception mentah. Untuk 401/405
                // isinya info routing yang tidak sensitif dan membantu debugging,
                // tapi untuk 500 bisa apa saja — pernah terjadi SQL utuh beserta
                // hash password. Jadi khusus 5xx: detail ke log, klien dapat kode.
                $detail = $e->getMessage();
                if ($err >= 500) {
                    $ref = strtoupper(bin2hex(random_bytes(4)));
                    \Illuminate\Support\Facades\Log::error("[{$ref}] " . $detail);
                    $msg    = "Terjadi kesalahan di server. Sertakan kode {$ref} saat melaporkannya.";
                    $detail = "";
                }

                return response()->json([
                    'resCode' => $err,
                    'resPhrase' => $msg,
                    'resStatus' => "fail",
                    'resMsg' => $msg,
                    'data' => $detail
                ], $err);
            // }
        });
    })->create();
