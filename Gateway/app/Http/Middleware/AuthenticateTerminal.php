<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi request scan dari TERMINAL (bukan user). Absen-by-scan hanya
 * diterima dari terminal terdaftar:
 *  - header X-Terminal-Id + X-Terminal-Token (dicek terhadap token_hash)
 *  - mode produksi : IP request harus di ip_allowlist (LAN sekolah)
 *  - mode demo     : body lat/lng harus di dalam geofence terminal
 * Terminal yang lolos dilampirkan ke request->attributes 'terminal'.
 */
class AuthenticateTerminal
{
    use ApiResponser;

    public function handle(Request $request, Closure $next): Response
    {
        $id    = $request->header('X-Terminal-Id');
        $token = $request->header('X-Terminal-Token');

        if (!$id || !$token) {
            return $this->response('Terminal tidak terautentikasi.', Response::HTTP_UNAUTHORIZED);
        }

        $terminal = Terminal::find($id);
        if (!$terminal || !$terminal->is_aktif || !$terminal->verifyToken($token)) {
            return $this->response('Terminal tidak dikenali atau nonaktif.', Response::HTTP_UNAUTHORIZED);
        }

        if ($terminal->mode === 'produksi') {
            if (!$terminal->ipAllowed($request->ip())) {
                return $this->response(
                    'Absen hanya bisa dari jaringan sekolah.',
                    Response::HTTP_FORBIDDEN
                );
            }
        } else { // demo — geofence
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            if (!$terminal->withinGeofence(
                $lat !== null ? (float) $lat : null,
                $lng !== null ? (float) $lng : null
            )) {
                return $this->response(
                    'Berada di luar area sekolah.',
                    Response::HTTP_FORBIDDEN,
                    ['lokasiDitolak' => true]
                );
            }
        }

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }
}
