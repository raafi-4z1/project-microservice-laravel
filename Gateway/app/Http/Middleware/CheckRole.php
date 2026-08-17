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
     * Pseudo-role untuk "Administrator Sekolah" (staf Tata Usaha).
     *
     * Bukan nilai `users.role` — pemiliknya tetap berrole `Karyawan` supaya
     * absensi pegawai dan hak baca karyawan tidak berubah. Yang membedakan
     * adalah flag `users.is_admin_sekolah`.
     *
     * Pemakaian di route: `->middleware('check.role:SuperAdmin,Admin,AdminSekolah')`
     * -> SuperAdmin & Admin lolos lewat role; karyawan lolos HANYA bila flag-nya
     * menyala. Karyawan biasa (satpam, kebersihan) tetap 403.
     */
    private const PSEUDO_ADMIN_SEKOLAH = 'AdminSekolah';

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if ($user) {
            if (in_array($user->role, $roles, true)) {
                return $next($request);
            }

            if (in_array(self::PSEUDO_ADMIN_SEKOLAH, $roles, true) && $user->isAdminSekolah()) {
                return $next($request);
            }
        }

        return $this->response(
            "You do not have permission to access this page.",
            Response::HTTP_FORBIDDEN
        );
    }
}
