<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    use ApiResponser;

    // Endpoint yang tetap boleh diakses saat wajib ganti password:
    // ganti password itu sendiri, lihat profil, dan keluar
    private const ALLOWED_PATHS = [
        'api/password',
        'api/user',
        'api/logout',
        'api/logout-all',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Dipasang SETELAH auth:api (route middleware) — user sudah ter-resolve.
        // Catatan: dipasang global tidak bekerja, karena middleware global
        // berjalan sebelum guard Passport aktif (user selalu null).
        $user = $request->user();

        // ?? false: aman sebelum migration dijalankan (kolom belum ada -> null)
        if ($user && ($user->must_change_password ?? false) && !in_array($request->path(), self::ALLOWED_PATHS)) {
            return $this->response(
                'Anda wajib mengganti password sebelum dapat mengakses fitur lain.',
                Response::HTTP_FORBIDDEN,
                ['mustChangePassword' => true]
            );
        }

        return $next($request);
    }
}
