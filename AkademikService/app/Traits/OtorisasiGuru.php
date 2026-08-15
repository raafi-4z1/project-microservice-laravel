<?php

namespace App\Traits;

use App\Models\PengampuMapel;
use App\Models\SemesterAktif;
use App\Models\WaliKelas;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi baca data akademik untuk role Guru.
 *
 * Gateway HANYA mengirim header X-Guru-Id ketika user yang login berrole Guru.
 * Jadi: header kosong = pemanggil admin/karyawan/internal -> tidak dibatasi.
 *
 * ATURAN LEVEL-KELAS = WALI SAJA.
 * Data sekelas untuk SEMUA mapel (raport, ranking, daftar nilai, rekap absensi
 * harian) hanya boleh dibuka guru yang menjadi WALI kelas itu — bukan sekadar
 * pengampu. Guru pengampu cukup melihat mapelnya sendiri lewat
 * `nilai/pengampu/{id}` (dicek terpisah oleh pastikanGuruBolehAksesPengampu).
 *
 * Alur absensi per pelajaran (`absensi/pelajaran/*`) TIDAK memakai trait ini —
 * di sana guru pengampu memang berhak mengabsen siswa di jam pelajarannya.
 *
 * Controller pemakai WAJIB juga `use ApiResponser` (dipakai untuk $this->response).
 */
trait OtorisasiGuru
{
    protected function guruIdDariHeader(Request $request): ?int
    {
        $raw = $request->header('X-Guru-Id');
        return ($raw === null || $raw === '') ? null : (int) $raw;
    }

    /**
     * Periode yang dipakai untuk mengecek penugasan guru.
     * Pakai tahun_ajaran+semester dari query kalau dikirim (supaya guru tetap
     * bisa membuka data historis kelas yang dulu ia ampu); kalau tidak, pakai
     * semester aktif.
     *
     * @return array{0:?string,1:?string}
     */
    protected function periodeOtorisasi(Request $request): array
    {
        if ($request->filled('tahun_ajaran') && $request->filled('semester')) {
            return [$request->input('tahun_ajaran'), (string) $request->input('semester')];
        }

        $aktif = SemesterAktif::where('is_aktif', true)->first();
        return $aktif ? [$aktif->tahun_ajaran, (string) $aktif->semester] : [null, null];
    }

    /**
     * Pastikan guru boleh membaca data SE-KELAS (semua mapel) di kelas ini.
     * Syaratnya: ia WALI kelas tersebut. Pengampu saja TIDAK cukup.
     * null = boleh; JsonResponse 403 = ditolak.
     */
    protected function pastikanGuruBolehAksesKelas(Request $request, int $kelasId): ?\Illuminate\Http\JsonResponse
    {
        $guruId = $this->guruIdDariHeader($request);
        if ($guruId === null) {
            return null; // bukan guru (admin/karyawan) -> tidak dibatasi
        }

        [$ta, $sem] = $this->periodeOtorisasi($request);
        if ($ta === null) {
            return $this->response('Belum ada semester aktif untuk memverifikasi akses guru.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wali = WaliKelas::where('guru_id', $guruId)
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran', $ta)
            ->where('semester', $sem)
            ->exists();

        if ($wali) {
            return null;
        }

        return $this->response(
            'Data sekelas hanya dapat dibuka oleh wali kelas. Untuk mata pelajaran yang Anda ampu, gunakan endpoint nilai per pengampu.',
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Pastikan guru boleh membaca nilai milik satu pengampu tertentu.
     * null = boleh; JsonResponse 403/404 = ditolak.
     */
    protected function pastikanGuruBolehAksesPengampu(Request $request, int $pengampuId): ?\Illuminate\Http\JsonResponse
    {
        $guruId = $this->guruIdDariHeader($request);
        if ($guruId === null) {
            return null;
        }

        $pengampu = PengampuMapel::find($pengampuId);
        if (!$pengampu) {
            return $this->response("Pengampu id:{$pengampuId} tidak ditemukan.", Response::HTTP_NOT_FOUND);
        }

        if ((int) $pengampu->guru_id !== $guruId) {
            return $this->response('Anda bukan pengampu mata pelajaran ini.', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Daftar kelas_id yang guru ini WALI-i pada periode berjalan.
     * Dipakai untuk MEMFILTER daftar, bukan menolak (mis. daftar izin keluar).
     *
     * Sengaja wali saja, bukan pengampu: izin keluar hanya boleh DISETUJUI wali
     * kelas (lihat AbsensiController::pastikanWaliKelas), jadi menampilkan siswa
     * dari kelas yang cuma ia ampu hanya memberi baris yang tak bisa ia tindak.
     *
     * @return array<int>
     */
    protected function kelasIdBolehAkses(Request $request, int $guruId): array
    {
        [$ta, $sem] = $this->periodeOtorisasi($request);
        if ($ta === null) {
            return [];
        }

        return array_values(array_unique(array_map('intval',
            WaliKelas::where('guru_id', $guruId)
                ->where('tahun_ajaran', $ta)->where('semester', $sem)
                ->pluck('kelas_id')->all()
        )));
    }
}
