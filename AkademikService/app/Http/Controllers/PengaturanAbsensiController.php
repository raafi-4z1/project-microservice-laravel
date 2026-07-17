<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\PengaturanAbsensi;
use App\Models\PeriodeKhusus;
use App\Traits\ApiResponser;

/**
 * Pengaturan absensi per semester, dengan opsi override per periode khusus.
 *  - periode_id null  = default semester (mis. batas terlambat 07:20)
 *  - periode_id terisi = berlaku selama periode itu (mis. Ramadan 08:00)
 *
 * Keunikan (tahun_ajaran, semester, periode_id) dijaga di sini karena unique
 * MySQL menganggap NULL saling berbeda.
 *
 * Catatan: kolom `radius_geofence_m` sengaja TIDAK diekspos — radius geofence
 * dipegang per-terminal (`terminals.radius_m` di Gateway) yang lebih tepat.
 */
class PengaturanAbsensiController extends Controller
{
    use ApiResponser;

    private const TZ = 'Asia/Jakarta';

    private const DEFAULT_JAM = [
        'jam_masuk_sekolah'       => '07:00:00',
        'batas_terlambat_siswa'   => '07:20:00',
        'jam_masuk_pegawai'       => '07:00:00',
        'batas_terlambat_pegawai' => '07:20:00',
        'durasi_pin_window_menit' => 10,
    ];

    // GET /akademik/pengaturan-absensi — filter: tahun_ajaran, semester
    public function index(Request $request)
    {
        try {
            $query = PengaturanAbsensi::with('periode');
            if ($request->filled('tahun_ajaran')) $query->where('tahun_ajaran', $request->tahun_ajaran);
            if ($request->filled('semester'))     $query->where('semester', $request->semester);

            $records = $query->orderBy('tahun_ajaran')->orderBy('semester')->orderBy('periode_id')
                ->get()->map(fn($r) => $this->toApiArray($r));

            return $this->response('Daftar pengaturan absensi.', Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /akademik/pengaturan-absensi/efektif?tanggal=
     * Pengaturan yang BENAR-BENAR berlaku pada sebuah tanggal (sudah
     * memperhitungkan periode khusus). Kalau belum ada baris sama sekali,
     * kembalikan default bawaan sistem.
     */
    public function efektif(Request $request)
    {
        try {
            $tanggal = $request->input('tanggal', Carbon::now(self::TZ)->toDateString());

            $tahunAjaran = $request->input('tahun_ajaran');
            $semester    = $request->input('semester');
            if (!$tahunAjaran || !$semester) {
                $aktif = \App\Models\SemesterAktif::where('is_aktif', true)->first();
                if (!$aktif) {
                    return $this->response('Belum ada semester aktif yang ditetapkan.', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $tahunAjaran = $tahunAjaran ?: $aktif->tahun_ajaran;
                $semester    = $semester    ?: $aktif->semester;
            }

            $periode = PeriodeKhusus::untukTanggal($tanggal);
            $row     = PengaturanAbsensi::efektif($tahunAjaran, $semester, $periode?->id);

            return $this->response("Pengaturan absensi berlaku pada {$tanggal}.", Response::HTTP_OK, [
                'tanggal'     => $tanggal,
                'tahunAjaran' => $tahunAjaran,
                'semester'    => (int) $semester,
                'periode'     => $periode ? [
                    'idPeriode' => $periode->id,
                    'nama'      => $periode->nama,
                    'jenis'     => $periode->jenis,
                    'kbmNormal' => (bool) $periode->kbm_normal,
                ] : null,
                'sumber'      => $row ? ($row->periode_id ? 'periode' : 'default_semester') : 'default_sistem',
                'pengaturan'  => $row ? $this->jamArray($row) : $this->defaultArray(),
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /akademik/pengaturan-absensi
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), $this->rules(true));
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $periodeId = $request->input('periode_id');
            if ($this->sudahAda($request->tahun_ajaran, $request->semester, $periodeId)) {
                return $this->response(
                    'Pengaturan untuk semester/periode ini sudah ada. Gunakan PATCH untuk mengubah.',
                    Response::HTTP_CONFLICT
                );
            }

            $record = PengaturanAbsensi::create(array_merge(
                ['tahun_ajaran' => $request->tahun_ajaran, 'semester' => $request->semester, 'periode_id' => $periodeId],
                $request->only(array_keys(self::DEFAULT_JAM))
            ));

            return $this->response('Pengaturan absensi berhasil dibuat.', Response::HTTP_CREATED, $this->toApiArray($record->fresh('periode')));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/pengaturan-absensi/{id}
    public function update(Request $request, $id)
    {
        try {
            $record = PengaturanAbsensi::find($id);
            if (!$record) {
                return $this->response("Pengaturan absensi id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), $this->rules(false));
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $data = $request->only(array_keys(self::DEFAULT_JAM));
            if (empty($data)) {
                return $this->response('Tidak ada field yang diubah.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $record->update($data);

            return $this->response('Pengaturan absensi berhasil diperbarui.', Response::HTTP_OK, $this->toApiArray($record->fresh('periode')));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/pengaturan-absensi/{id}
    public function destroy($id)
    {
        try {
            $record = PengaturanAbsensi::find($id);
            if (!$record) {
                return $this->response("Pengaturan absensi id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }
            $record->delete();

            return $this->response('Pengaturan absensi berhasil dihapus.', Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        return [
            'tahun_ajaran'            => $create ? ['required', 'regex:/^\d{4}\/\d{4}$/'] : ['prohibited'],
            'semester'                => $create ? 'required|in:1,2' : 'prohibited',
            'periode_id'              => $create ? 'nullable|integer|exists:periode_khusus,id' : 'prohibited',
            'jam_masuk_sekolah'       => 'sometimes|date_format:H:i',
            'batas_terlambat_siswa'   => 'sometimes|date_format:H:i',
            'jam_masuk_pegawai'       => 'sometimes|date_format:H:i',
            'batas_terlambat_pegawai' => 'sometimes|date_format:H:i',
            'durasi_pin_window_menit' => 'sometimes|integer|min:1|max:60',
        ];
    }

    private function sudahAda(string $ta, $sem, ?int $periodeId): bool
    {
        return PengaturanAbsensi::where('tahun_ajaran', $ta)
            ->where('semester', $sem)
            ->where(fn($q) => $periodeId === null ? $q->whereNull('periode_id') : $q->where('periode_id', $periodeId))
            ->exists();
    }

    private function jamArray(PengaturanAbsensi $r): array
    {
        return [
            'jamMasukSekolah'      => $r->jam_masuk_sekolah,
            'batasTerlambatSiswa'  => $r->batas_terlambat_siswa,
            'jamMasukPegawai'      => $r->jam_masuk_pegawai,
            'batasTerlambatPegawai' => $r->batas_terlambat_pegawai,
            'durasiPinWindowMenit' => (int) $r->durasi_pin_window_menit,
        ];
    }

    private function defaultArray(): array
    {
        return [
            'jamMasukSekolah'      => self::DEFAULT_JAM['jam_masuk_sekolah'],
            'batasTerlambatSiswa'  => self::DEFAULT_JAM['batas_terlambat_siswa'],
            'jamMasukPegawai'      => self::DEFAULT_JAM['jam_masuk_pegawai'],
            'batasTerlambatPegawai' => self::DEFAULT_JAM['batas_terlambat_pegawai'],
            'durasiPinWindowMenit' => self::DEFAULT_JAM['durasi_pin_window_menit'],
        ];
    }

    private function toApiArray(PengaturanAbsensi $r): array
    {
        return array_merge([
            'idPengaturanAbsensi' => $r->id,
            'tahunAjaran'         => $r->tahun_ajaran,
            'semester'            => (int) $r->semester,
            'periodeId'           => $r->periode_id,
            'periodeNama'         => $r->periode?->nama,
            'lingkup'             => $r->periode_id ? 'periode' : 'default_semester',
        ], $this->jamArray($r));
    }
}
