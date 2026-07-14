<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\AbsensiHarian;
use App\Models\AbsensiPegawai;
use App\Models\PengaturanAbsensi;
use App\Models\SemesterAktif;
use App\Traits\ApiResponser;

/**
 * Pencatatan absensi (dipanggil oleh Gateway).
 * Waktu selalu memakai jam server (now()), bukan jam yang dikirim terminal,
 * agar tidak bisa dimanipulasi. Idempotent per (subjek, tanggal).
 */
class AbsensiController extends Controller
{
    use ApiResponser;

    // Zona waktu sekolah. AkademikService berjalan di UTC, sedangkan ambang
    // terlambat & batas hari absensi adalah jam dinding WIB — hitung eksplisit
    // agar tidak salah klasifikasi dan batas "hari" tidak bergeser di 07:00 WIB.
    private const TZ = 'Asia/Jakarta';

    // Default bila belum ada baris pengaturan_absensi untuk semester aktif
    private const DEFAULT_BATAS_SISWA   = '07:20:00';
    private const DEFAULT_BATAS_PEGAWAI = '07:20:00';

    // POST /akademik/absensi/scan-siswa — absen masuk gerbang (siswa)
    public function scanSiswa(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'siswa_id'    => 'required|integer|min:1',
                'terminal_id' => 'nullable|integer|min:1',
                'metode'      => 'nullable|in:scan,manual',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $semester = SemesterAktif::where('is_aktif', true)->first();
            if (!$semester) {
                return $this->response('Belum ada semester aktif yang ditetapkan.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $now     = Carbon::now(self::TZ);
            $tanggal = $now->toDateString();

            // Idempotent: kalau sudah absen hari ini, kembalikan yang ada
            $existing = AbsensiHarian::where('siswa_id', $request->siswa_id)
                ->where('tanggal', $tanggal)
                ->first();
            if ($existing) {
                return $this->response(
                    'Siswa sudah absen masuk hari ini.',
                    Response::HTTP_OK,
                    $this->toApiArraySiswa($existing, true)
                );
            }

            $pengaturan = $this->getPengaturan($semester->tahun_ajaran, $semester->semester);
            $batas      = $pengaturan->batas_terlambat_siswa ?? self::DEFAULT_BATAS_SISWA;
            $status     = $this->tepatAtauTerlambat($now, $tanggal, $batas);

            $record = AbsensiHarian::create([
                'siswa_id'     => $request->siswa_id,
                'tanggal'      => $tanggal,
                'tahun_ajaran' => $semester->tahun_ajaran,
                'semester'     => $semester->semester,
                'jam_masuk'    => $now,
                'status'       => $status,
                'metode'       => $request->input('metode', 'scan'),
                'terminal_id'  => $request->terminal_id,
                'dicatat_oleh' => $request->input('dicatat_oleh'),
            ]);

            return $this->response(
                $status === 'terlambat' ? 'Absen tercatat — TERLAMBAT.' : 'Absen masuk tercatat.',
                Response::HTTP_CREATED,
                $this->toApiArraySiswa($record, false)
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /akademik/absensi/scan-pegawai — absen masuk (guru/karyawan)
    public function scanPegawai(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'subjek_tipe' => 'required|in:guru,karyawan',
                'subjek_id'   => 'required|integer|min:1',
                'terminal_id' => 'nullable|integer|min:1',
                'metode'      => 'nullable|in:scan,pin,manual',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $semester = SemesterAktif::where('is_aktif', true)->first();
            if (!$semester) {
                return $this->response('Belum ada semester aktif yang ditetapkan.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $now     = Carbon::now(self::TZ);
            $tanggal = $now->toDateString();

            $existing = AbsensiPegawai::where('subjek_tipe', $request->subjek_tipe)
                ->where('subjek_id', $request->subjek_id)
                ->where('tanggal', $tanggal)
                ->first();
            if ($existing) {
                return $this->response(
                    'Pegawai sudah absen masuk hari ini.',
                    Response::HTTP_OK,
                    $this->toApiArrayPegawai($existing, true)
                );
            }

            $pengaturan = $this->getPengaturan($semester->tahun_ajaran, $semester->semester);
            $batas      = $pengaturan->batas_terlambat_pegawai ?? self::DEFAULT_BATAS_PEGAWAI;
            $status     = $this->tepatAtauTerlambat($now, $tanggal, $batas);

            $record = AbsensiPegawai::create([
                'subjek_tipe'   => $request->subjek_tipe,
                'subjek_id'     => $request->subjek_id,
                'tanggal'       => $tanggal,
                'jam_masuk'     => $now,
                'status'        => $status,
                'metode'        => $request->input('metode', 'scan'),
                'terminal_id'   => $request->terminal_id,
                'pin_window_id' => $request->input('pin_window_id'),
                'dicatat_oleh'  => $request->input('dicatat_oleh'),
            ]);

            return $this->response(
                $status === 'terlambat' ? 'Absen tercatat — TERLAMBAT.' : 'Absen masuk tercatat.',
                Response::HTTP_CREATED,
                $this->toApiArrayPegawai($record, false)
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    private function getPengaturan(string $tahunAjaran, $semester): ?PengaturanAbsensi
    {
        return PengaturanAbsensi::where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();
    }

    // hadir bila <= batas, selain itu terlambat (ambang jam dinding WIB)
    private function tepatAtauTerlambat(Carbon $now, string $tanggal, string $batas): string
    {
        $ambang = Carbon::parse("{$tanggal} {$batas}", self::TZ);
        return $now->lessThanOrEqualTo($ambang) ? 'hadir' : 'terlambat';
    }

    private function toApiArraySiswa(AbsensiHarian $r, bool $sudahAbsen): array
    {
        return [
            'idAbsensi'  => $r->id,
            'subjek'     => 'siswa',
            'siswaId'    => $r->siswa_id,
            'tanggal'    => $r->tanggal instanceof \Carbon\Carbon ? $r->tanggal->toDateString() : $r->tanggal,
            'jamMasuk'   => $r->jam_masuk instanceof \Carbon\Carbon ? $r->jam_masuk->toDateTimeString() : $r->jam_masuk,
            'status'     => $r->status,
            'metode'     => $r->metode,
            'terminalId' => $r->terminal_id,
            'sudahAbsen' => $sudahAbsen,
        ];
    }

    private function toApiArrayPegawai(AbsensiPegawai $r, bool $sudahAbsen): array
    {
        return [
            'idAbsensi'  => $r->id,
            'subjek'     => $r->subjek_tipe,
            'subjekId'   => $r->subjek_id,
            'tanggal'    => $r->tanggal instanceof \Carbon\Carbon ? $r->tanggal->toDateString() : $r->tanggal,
            'jamMasuk'   => $r->jam_masuk instanceof \Carbon\Carbon ? $r->jam_masuk->toDateTimeString() : $r->jam_masuk,
            'status'     => $r->status,
            'metode'     => $r->metode,
            'terminalId' => $r->terminal_id,
            'sudahAbsen' => $sudahAbsen,
        ];
    }
}
