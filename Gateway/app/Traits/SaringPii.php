<?php

namespace App\Traits;

use Illuminate\Http\Response;

/**
 * Menyaring respons direktori menjadi "info publik" saja.
 *
 * Keputusan produk: direktori guru/karyawan/siswa adalah info sekolah — boleh
 * dibuka seluruh warga sekolah. Yang TIDAK boleh ikut adalah data pribadi:
 * NIK, alamat, telepon, tanggal lahir, NISN, data orang tua. Hanya PENGELOLA
 * (SuperAdmin/Admin/Administrator Sekolah) — plus guru khusus untuk data siswa —
 * yang menerima versi penuh.
 *
 * Penyaringan wajib di sisi server. Menyembunyikannya di UI saja tidak cukup:
 * klien bisa dimodifikasi, dan aplikasi menampilkan apa pun field yang dikirim.
 *
 * Cara pakai: whitelist field yang BOLEH keluar (bukan blacklist yang harus
 * dilarang). Kolom baru di service otomatis ikut tersaring, bukan otomatis bocor.
 */
trait SaringPii
{
    /**
     * Saring respons DETAIL (data berupa satu objek).
     *
     * Record MILIK PEMANGGIL SENDIRI tidak disaring. Tanpa pengecualian ini,
     * seorang karyawan tidak bisa melihat alamat & nomor teleponnya sendiri di
     * layar profil — padahal yang hendak dilindungi adalah data ORANG LAIN.
     * Kecocokan diambil dari email pada payload service dibandingkan email di
     * token, bukan dari id yang dikirim klien.
     *
     * @param  \Illuminate\Http\Response  $response  hasil performRequest()
     * @param  list<string>              $fields    field yang boleh keluar
     */
    protected function saringDetail($response, array $fields)
    {
        $decode = $this->decodeUntukSaring($response);

        if (($decode['resCode'] ?? null) !== Response::HTTP_OK || !is_array($decode['data'] ?? null)) {
            return $response;
        }

        $emailSendiri = auth()->user()->email ?? null;
        $emailRecord  = $decode['data']['email'] ?? null;
        if ($emailSendiri !== null && $emailRecord !== null && hash_equals((string) $emailSendiri, (string) $emailRecord)) {
            return $response;
        }

        return $this->response(
            $decode['resMsg'] ?? 'OK',
            Response::HTTP_OK,
            array_intersect_key($decode['data'], array_flip($fields))
        );
    }

    /**
     * Saring respons DAFTAR berpaginasi (data berupa envelope dengan `data` array).
     *
     * Meta paginasi (current_page, total, links, dst.) dipertahankan apa adanya
     * supaya klien tidak perlu membedakan bentuk respons per role.
     *
     * @param  \Illuminate\Http\Response  $response
     * @param  list<string>              $fields
     */
    protected function saringDaftar($response, array $fields)
    {
        $decode = $this->decodeUntukSaring($response);

        if (($decode['resCode'] ?? null) !== Response::HTTP_OK || !is_array($decode['data']['data'] ?? null)) {
            return $response;
        }

        $flip = array_flip($fields);
        $data = $decode['data'];
        $data['data'] = array_map(
            fn($baris) => is_array($baris) ? array_intersect_key($baris, $flip) : $baris,
            $data['data']
        );

        return $this->response($decode['resMsg'] ?? 'OK', Response::HTTP_OK, $data);
    }

    /**
     * performRequest() mengembalikan Illuminate\Http\Response, tapi respons yang
     * dibentuk sendiri lewat ApiResponser adalah JsonResponse. Keduanya turunan
     * Symfony Response — pakai tipe induknya supaya tidak ada bentuk yang lolos
     * tanpa disaring (pernah terjadi: decode yang terlalu sempit membuat isinya
     * jatuh ke [] dan respons balik 500).
     */
    private function decodeUntukSaring($response): array
    {
        $raw = $response instanceof \Symfony\Component\HttpFoundation\Response
            ? $response->getContent()
            : $response;

        return json_decode($raw, true) ?? [];
    }
}
