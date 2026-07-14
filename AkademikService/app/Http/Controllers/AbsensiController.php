<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\AbsensiHarian;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\PengaturanAbsensi;
use App\Models\SemesterAktif;
use App\Models\SiswaKelas;
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

    // ── Absensi per pelajaran (guru ceklis siswa saat jam pelajarannya) ───────

    // GET /akademik/absensi/pelajaran/sekarang — jadwal guru yang berlangsung SEKARANG
    // + daftar siswa kelas itu beserta status yang sudah/belum ditandai hari ini.
    public function pelajaranSekarang(Request $request)
    {
        try {
            $guruId = $this->guruIdDari($request);
            if (!$guruId) {
                return $this->response('guru_id tidak diketahui.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $semester = SemesterAktif::where('is_aktif', true)->first();
            if (!$semester) {
                return $this->response('Belum ada semester aktif yang ditetapkan.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $now     = Carbon::now(self::TZ);
            $tanggal = $now->toDateString();
            $hari    = $this->hariIndo($now);
            $nowTime = $now->format('H:i:s');

            if (!in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true)) {
                return $this->response('Bukan hari sekolah.', Response::HTTP_OK, []);
            }

            $jadwal = JadwalPelajaran::with(['jamMulai', 'jamSelesai', 'pengampuMapel'])
                ->where('hari', $hari)
                ->whereHas('pengampuMapel', function ($q) use ($guruId, $semester) {
                    $q->where('guru_id', $guruId)
                        ->where('tahun_ajaran', $semester->tahun_ajaran)
                        ->where('semester', $semester->semester);
                })
                ->get()
                ->first(function ($j) use ($nowTime) {
                    return $j->jamMulai && $j->jamSelesai
                        && $j->jamMulai->jam_mulai <= $nowTime
                        && $j->jamSelesai->jam_selesai >= $nowTime;
                });

            if (!$jadwal) {
                return $this->response('Tidak ada jam pelajaran Anda saat ini.', Response::HTTP_OK, []);
            }

            $data = $this->jadwalInfo($jadwal, $tanggal);
            $data['siswa'] = $this->siswaListPelajaran($jadwal, $tanggal);

            return $this->response('Jam pelajaran berlangsung.', Response::HTTP_OK, $data);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/absensi/pelajaran/{jadwal_id}/siswa — daftar siswa + status utk 1 jadwal
    public function daftarSiswaJadwal(Request $request, $jadwalId)
    {
        try {
            $jadwal = JadwalPelajaran::with(['jamMulai', 'jamSelesai', 'pengampuMapel'])->find($jadwalId);
            if (!$jadwal) {
                return $this->response("Jadwal pelajaran id:{$jadwalId} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            // Kalau dipanggil atas nama guru, pastikan jadwal ini miliknya
            $guruId = $this->guruIdDari($request);
            if ($guruId && (int) optional($jadwal->pengampuMapel)->guru_id !== (int) $guruId) {
                return $this->response('Bukan jam pelajaran Anda.', Response::HTTP_FORBIDDEN);
            }

            $tanggal = $request->input('tanggal', Carbon::now(self::TZ)->toDateString());

            $data = $this->jadwalInfo($jadwal, $tanggal);
            $data['siswa'] = $this->siswaListPelajaran($jadwal, $tanggal);

            return $this->response("Daftar siswa jadwal id:{$jadwalId}.", Response::HTTP_OK, $data);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /akademik/absensi/pelajaran/tandai — guru menandai absensi siswa
    public function tandaiPelajaran(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'jadwal_id'          => 'required|integer|min:1',
                'tanggal'            => 'nullable|date',
                'absensi'            => 'required|array|min:1',
                'absensi.*.siswa_id' => 'required|integer|min:1',
                'absensi.*.status'   => 'required|in:hadir,izin,sakit,alpa',
                'absensi.*.keterangan' => 'nullable|string|max:255',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $jadwal = JadwalPelajaran::with('pengampuMapel')->find($request->jadwal_id);
            if (!$jadwal || !$jadwal->pengampuMapel) {
                return $this->response("Jadwal pelajaran id:{$request->jadwal_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $pengampu = $jadwal->pengampuMapel;
            $guruId   = $this->guruIdDari($request);
            if ($guruId && (int) $pengampu->guru_id !== (int) $guruId) {
                return $this->response('Bukan jam pelajaran Anda.', Response::HTTP_FORBIDDEN);
            }

            $tanggal = $request->input('tanggal', Carbon::now(self::TZ)->toDateString());

            // Batasi ke siswa yang memang terdaftar di kelas jadwal ini
            $siswaKelas = SiswaKelas::where('kelas_id', $pengampu->kelas_id)
                ->where('tahun_ajaran', $pengampu->tahun_ajaran)
                ->where('semester', $pengampu->semester)
                ->pluck('siswa_id')
                ->flip();

            $tersimpan = 0;
            $dilewati  = [];
            foreach ($request->absensi as $item) {
                if (!$siswaKelas->has($item['siswa_id'])) {
                    $dilewati[] = $item['siswa_id'];
                    continue;
                }
                AbsensiPelajaran::updateOrCreate(
                    [
                        'jadwal_id' => $jadwal->id,
                        'siswa_id'  => $item['siswa_id'],
                        'tanggal'   => $tanggal,
                    ],
                    [
                        'status'       => $item['status'],
                        'keterangan'   => $item['keterangan'] ?? null,
                        'dicatat_oleh' => $guruId,
                        'tahun_ajaran' => $pengampu->tahun_ajaran,
                        'semester'     => $pengampu->semester,
                    ]
                );
                $tersimpan++;
            }

            return $this->response('Absensi pelajaran tersimpan.', Response::HTTP_OK, [
                'jadwalId'  => $jadwal->id,
                'tanggal'   => $tanggal,
                'tersimpan' => $tersimpan,
                'dilewati'  => $dilewati, // siswa_id di luar kelas ini
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    // guru_id dari header X-Guru-Id (diinject Gateway) atau param guru_id
    private function guruIdDari(Request $request): ?int
    {
        $id = $request->header('X-Guru-Id') ?: $request->input('guru_id');
        return $id ? (int) $id : null;
    }

    private function hariIndo(Carbon $now): string
    {
        return [
            Carbon::SUNDAY    => 'Minggu',
            Carbon::MONDAY    => 'Senin',
            Carbon::TUESDAY   => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY  => 'Kamis',
            Carbon::FRIDAY    => 'Jumat',
            Carbon::SATURDAY  => 'Sabtu',
        ][$now->dayOfWeek];
    }

    private function jadwalInfo(JadwalPelajaran $jadwal, string $tanggal): array
    {
        $pm = $jadwal->pengampuMapel;
        $jm = $jadwal->jamMulai;
        $js = $jadwal->jamSelesai;

        return [
            'jadwalId'    => $jadwal->id,
            'guruId'      => $pm?->guru_id,
            'mapelId'     => $pm?->mapel_id,
            'kelasId'     => $pm?->kelas_id,
            'hari'        => $jadwal->hari,
            'pukul'       => ($jm && $js) ? "{$jm->jam_mulai} - {$js->jam_selesai}" : null,
            'tahunAjaran' => $pm?->tahun_ajaran,
            'semester'    => $pm?->semester,
            'tanggal'     => $tanggal,
        ];
    }

    // Daftar siswa kelas jadwal + status absensi_pelajaran (null bila belum ditandai)
    private function siswaListPelajaran(JadwalPelajaran $jadwal, string $tanggal): array
    {
        $pm = $jadwal->pengampuMapel;

        $siswaIds = SiswaKelas::where('kelas_id', $pm->kelas_id)
            ->where('tahun_ajaran', $pm->tahun_ajaran)
            ->where('semester', $pm->semester)
            ->pluck('siswa_id');

        $marks = AbsensiPelajaran::where('jadwal_id', $jadwal->id)
            ->where('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        return $siswaIds->map(fn($sid) => [
            'siswaId'    => $sid,
            'status'     => optional($marks->get($sid))->status,
            'keterangan' => optional($marks->get($sid))->keterangan,
        ])->values()->all();
    }

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
