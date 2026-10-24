<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak berkas unggahan yang gagal SEBELUM autentikasi berjalan.
 *
 * Passport (`auth:api`) mengubah request menjadi PSR-7 untuk membaca token, dan
 * dalam proses itu membuka setiap berkas unggahan lewat getRealPath(). Berkas yang
 * ditolak PHP (mis. foto 3 MB melewati upload_max_filesize 2 MB) tidak punya path,
 * sehingga fopen("") meledak menjadi 500 "kesalahan server" — padahal kesalahannya
 * di sisi klien. Wajib middleware GLOBAL: middleware route baru jalan setelah auth.
 */
class TolakUploadGagal
{
    use ApiResponser;

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->berkas($request->allFiles()) as $field => $file) {
            if (!$file->isValid()) {
                $kode = $file->getError();
                if (in_array($kode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    $mb  = round(UploadedFile::getMaxFilesize() / 1048576, 1);
                    $msg = "Ukuran berkas {$field} melebihi batas unggah server (maks {$mb} MB).";
                    return $this->response($msg, Response::HTTP_UNPROCESSABLE_ENTITY, [$field => [$msg]]);
                }
                if ($kode === UPLOAD_ERR_PARTIAL) {
                    $msg = "Berkas {$field} hanya terunggah sebagian. Coba unggah ulang.";
                    return $this->response($msg, Response::HTTP_UNPROCESSABLE_ENTITY, [$field => [$msg]]);
                }
                // NO_TMP_DIR / CANT_WRITE / EXTENSION: kesalahan server, bukan klien.
                return $this->response("Upload {$field} gagal di server: {$file->getErrorMessage()}",
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            // Lolos PHP tapi path-nya tak bisa di-resolve: terjadi bila upload_tmp_dir
            // menunjuk folder yang bisa ditulis tapi tak bisa di-list (C:\Windows\Temp).
            // Tanpa cek ini gejalanya hanya "Unable to open ''" di log — sulit dilacak.
            if ($file->getRealPath() === false) {
                return $this->response("Berkas {$field} tak bisa dibaca server (realpath gagal) — periksa upload_tmp_dir di php.ini.",
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return $next($request);
    }

    /** @return iterable<string, UploadedFile> nama field bertitik (foto, lampiran.0) => berkas */
    private function berkas(array $files, string $awalan = ''): iterable
    {
        foreach ($files as $nama => $isi) {
            $kunci = $awalan === '' ? (string) $nama : "{$awalan}.{$nama}";
            if ($isi instanceof UploadedFile) {
                yield $kunci => $isi;
            } elseif (is_array($isi)) {
                yield from $this->berkas($isi, $kunci);
            }
        }
    }
}
