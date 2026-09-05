<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kurung akun "Petugas Acara" ke urusan agenda saja.
 *
 * KENAPA MIDDLEWARE TERPISAH, bukan cukup `check.role`:
 * `check.role` hanya MEMBERI hak — ia tak pernah mencabut. Sebagian besar rute
 * baca di sistem ini sengaja tidak punya gate sama sekali (direktori guru/kelas/
 * mapel, semester aktif, jadwal, periode), karena memang info sekolah untuk semua
 * warga. Akibatnya, memberi seseorang flag petugas acara TIDAK otomatis menutup
 * apa pun; ia tetap bisa membaca semua yang terbuka untuk pemegang token biasa.
 *
 * Permintaannya eksplisit: petugas acara **403 di semua endpoint selain acara +
 * profil sendiri**. Itu hanya bisa dicapai dengan daftar-BOLEH (allowlist) yang
 * ditolak secara default — bukan daftar-larang, yang akan bocor tiap kali ada
 * rute baru ditambahkan.
 *
 * Catatan penting: pembatasan ini hanya berlaku untuk akun yang HANYA petugas
 * acara. Kalau seseorang juga Admin/SuperAdmin/Administrator Sekolah, flag ini
 * tidak boleh menyempitkan haknya — mis. kepala TU yang merangkap panitia.
 */
class BatasiPetugasAcara
{
    use ApiResponser;

    /**
     * Yang tetap boleh diakses. Dicocokkan dengan Request::is() (mendukung `*`),
     * relatif terhadap path penuh termasuk prefix `api/`.
     */
    private const DIIZINKAN = [
        // Inti pekerjaannya
        'api/akademik/acara',
        'api/akademik/acara/*',

        // Perlu untuk memilih tanggal: ia harus bisa MELIHAT periode yang
        // memblokir acaranya, kalau tidak pesan bentrok jadi buntu.
        'api/akademik/periode',
        'api/akademik/periode/aktif',
        'api/akademik/semester/aktif',

        // Profil & sesi sendiri
        'api/user',
        'api/logout',
        'api/logout-all',
        'api/refresh',
        'api/password',
    ];

    /** Metode tulis yang tetap dilarang pada rute periode/semester di atas. */
    private const BACA_SAJA = [
        'api/akademik/periode',
        'api/akademik/periode/aktif',
        'api/akademik/semester/aktif',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Bukan petugas acara, atau punya hak lain yang lebih luas -> lewat.
        if (!$user || !$user->isPetugasAcara()) {
            return $next($request);
        }
        if (in_array($user->role, ['SuperAdmin', 'Admin'], true) || $user->isAdminSekolah()) {
            return $next($request);
        }

        foreach (self::DIIZINKAN as $pola) {
            if (!$request->is($pola)) {
                continue;
            }

            // Rute rujukan hanya boleh dibaca — jangan sampai petugas acara
            // bisa menggeser pekan ujian yang justru membatasi dirinya.
            if (in_array($pola, self::BACA_SAJA, true) && !$request->isMethodSafe()) {
                break;
            }

            return $next($request);
        }

        return $this->response(
            'Akun Petugas Acara hanya dapat mengelola agenda sekolah (akademik/acara).',
            Response::HTTP_FORBIDDEN
        );
    }
}
