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
 * Header ada = guru -> hanya boleh kelas yang ia AMPU (pengampu) atau ia WALI-i.
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
     * Pastikan guru boleh membaca data kelas ini.
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

        $mengampu = PengampuMapel::where('guru_id', $guruId)
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran', $ta)
            ->where('semester', $sem)
            ->exists();

        if ($mengampu) {
            return null;
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
            'Anda bukan pengampu maupun wali kelas ini pada periode tersebut.',
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
     * Daftar kelas_id yang boleh diakses guru pada periode berjalan
     * (gabungan kelas yang diampu + kelas yang diwali-i).
     * Dipakai untuk MEMFILTER daftar, bukan menolak (mis. daftar izin keluar).
     *
     * @return array<int>
     */
    protected function kelasIdBolehAkses(Request $request, int $guruId): array
    {
        [$ta, $sem] = $this->periodeOtorisasi($request);
        if ($ta === null) {
            return [];
        }

        $ampu = PengampuMapel::where('guru_id', $guruId)
            ->where('tahun_ajaran', $ta)->where('semester', $sem)
            ->pluck('kelas_id')->all();

        $wali = WaliKelas::where('guru_id', $guruId)
            ->where('tahun_ajaran', $ta)->where('semester', $sem)
            ->pluck('kelas_id')->all();

        return array_values(array_unique(array_map('intval', array_merge($ampu, $wali))));
    }
}
