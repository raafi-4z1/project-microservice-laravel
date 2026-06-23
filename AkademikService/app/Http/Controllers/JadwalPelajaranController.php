<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\PengampuMapel;
use App\Traits\ApiResponser;

class JadwalPelajaranController extends Controller
{
    use ApiResponser;

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'pengampu_mapel_id' => 'required|integer|min:1',
                'hari'              => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_mulai_id'      => 'required|integer|min:1',
                'jam_selesai_id'    => 'required|integer|min:1',
                'ruangan'           => 'nullable|string|max:50',
                'catatan'           => 'nullable|string|max:500',
            ], [
                'hari.in' => 'Hari harus Senin, Selasa, Rabu, Kamis, atau Jumat.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $pengampu = PengampuMapel::find($request->pengampu_mapel_id);
            if (!$pengampu) {
                return $this->response("Data pengampu mapel dengan id:{$request->pengampu_mapel_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $jamMulai   = JamPelajaran::find($request->jam_mulai_id);
            $jamSelesai = JamPelajaran::find($request->jam_selesai_id);

            if (!$jamMulai) {
                return $this->response("Jam mulai dengan id:{$request->jam_mulai_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }
            if (!$jamSelesai) {
                return $this->response("Jam selesai dengan id:{$request->jam_selesai_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }
            if ($jamMulai->ke >= $jamSelesai->ke) {
                return $this->response("Jam selesai (ke-{$jamSelesai->ke}) harus lebih besar dari jam mulai (ke-{$jamMulai->ke}).", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($this->hasKelasConflict($pengampu->kelas_id, $pengampu->tahun_ajaran, $pengampu->semester, $request->hari, $jamMulai->ke, $jamSelesai->ke)) {
                return $this->response(
                    "Kelas ini sudah memiliki jadwal yang bentrok di hari {$request->hari} pada jam tersebut.",
                    Response::HTTP_CONFLICT
                );
            }

            if ($this->hasGuruConflict($pengampu->guru_id, $pengampu->tahun_ajaran, $pengampu->semester, $request->hari, $jamMulai->ke, $jamSelesai->ke)) {
                return $this->response(
                    "Guru ini sudah mengajar di tempat lain pada hari {$request->hari} jam tersebut.",
                    Response::HTTP_CONFLICT
                );
            }

            // Jika ada record soft-deleted dengan slot yang sama, restore dan update
            // agar tidak melanggar unique constraint (pengampu_mapel_id, hari, jam_mulai_id)
            $trashed = JadwalPelajaran::withTrashed()
                ->where('pengampu_mapel_id', $request->pengampu_mapel_id)
                ->where('hari', $request->hari)
                ->where('jam_mulai_id', $request->jam_mulai_id)
                ->first();

            if ($trashed) {
                $trashed->restore();
                $trashed->update([
                    'jam_selesai_id' => $request->jam_selesai_id,
                    'ruangan'        => $request->ruangan,
                    'catatan'        => $request->catatan,
                ]);
                $record = $trashed->fresh(['jamMulai', 'jamSelesai', 'pengampuMapel']);
            } else {
                $record = JadwalPelajaran::create([
                    'pengampu_mapel_id' => $request->pengampu_mapel_id,
                    'hari'              => $request->hari,
                    'jam_mulai_id'      => $request->jam_mulai_id,
                    'jam_selesai_id'    => $request->jam_selesai_id,
                    'ruangan'           => $request->ruangan,
                    'catatan'           => $request->catatan,
                ]);
                $record->load(['jamMulai', 'jamSelesai', 'pengampuMapel']);
            }

            return $this->response(
                "Jadwal pelajaran berhasil ditambahkan.",
                Response::HTTP_CREATED,
                $this->toApiArray($record)
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $record = JadwalPelajaran::find($id);
            if (!$record) {
                return $this->response("Jadwal pelajaran dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'hari'           => 'in:Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_mulai_id'   => 'integer|min:1',
                'jam_selesai_id' => 'integer|min:1',
                'ruangan'        => 'nullable|string|max:50',
                'catatan'        => 'nullable|string|max:500',
            ], [
                'hari.in' => 'Hari harus Senin, Selasa, Rabu, Kamis, atau Jumat.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $hari      = $request->input('hari', $record->hari);
            $mulaiId   = $request->input('jam_mulai_id', $record->jam_mulai_id);
            $selesaiId = $request->input('jam_selesai_id', $record->jam_selesai_id);

            // Jalankan cek bentrok hanya jika hari atau jam berubah
            $hariAtauJamBerubah = $request->has('hari') || $request->has('jam_mulai_id') || $request->has('jam_selesai_id');

            if ($hariAtauJamBerubah) {
                $jamMulai   = JamPelajaran::find($mulaiId);
                $jamSelesai = JamPelajaran::find($selesaiId);

                if (!$jamMulai) {
                    return $this->response("Jam mulai dengan id:{$mulaiId} tidak ditemukan.", Response::HTTP_NOT_FOUND);
                }
                if (!$jamSelesai) {
                    return $this->response("Jam selesai dengan id:{$selesaiId} tidak ditemukan.", Response::HTTP_NOT_FOUND);
                }
                if ($jamMulai->ke >= $jamSelesai->ke) {
                    return $this->response("Jam selesai (ke-{$jamSelesai->ke}) harus lebih besar dari jam mulai (ke-{$jamMulai->ke}).", Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $pengampu = PengampuMapel::find($record->pengampu_mapel_id);

                if ($this->hasKelasConflict($pengampu->kelas_id, $pengampu->tahun_ajaran, $pengampu->semester, $hari, $jamMulai->ke, $jamSelesai->ke, (int) $id)) {
                    return $this->response(
                        "Kelas ini sudah memiliki jadwal yang bentrok di hari {$hari} pada jam tersebut.",
                        Response::HTTP_CONFLICT
                    );
                }

                if ($this->hasGuruConflict($pengampu->guru_id, $pengampu->tahun_ajaran, $pengampu->semester, $hari, $jamMulai->ke, $jamSelesai->ke, (int) $id)) {
                    return $this->response(
                        "Guru ini sudah mengajar di tempat lain pada hari {$hari} jam tersebut.",
                        Response::HTTP_CONFLICT
                    );
                }
            }

            $updateData = [];
            if ($request->has('hari'))           $updateData['hari']           = $request->hari;
            if ($request->has('jam_mulai_id'))   $updateData['jam_mulai_id']   = $request->jam_mulai_id;
            if ($request->has('jam_selesai_id')) $updateData['jam_selesai_id'] = $request->jam_selesai_id;
            if ($request->has('ruangan'))        $updateData['ruangan']        = $request->ruangan;
            if ($request->has('catatan'))        $updateData['catatan']        = $request->catatan;

            if (empty($updateData)) {
                return $this->response('Tidak ada field yang diubah.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Jika hari atau jam_mulai berubah, hapus permanent record soft-deleted
            // yang memiliki key baru agar tidak melanggar unique constraint
            if (array_key_exists('hari', $updateData) || array_key_exists('jam_mulai_id', $updateData)) {
                $newHari    = $updateData['hari']         ?? $record->hari;
                $newMulaiId = $updateData['jam_mulai_id'] ?? $record->jam_mulai_id;
                JadwalPelajaran::withTrashed()
                    ->where('pengampu_mapel_id', $record->pengampu_mapel_id)
                    ->where('hari', $newHari)
                    ->where('jam_mulai_id', $newMulaiId)
                    ->where('id', '!=', (int) $id)
                    ->whereNotNull('deleted_at')
                    ->forceDelete();
            }

            $record->update($updateData);
            $record->load(['jamMulai', 'jamSelesai', 'pengampuMapel']);

            return $this->response(
                "Jadwal pelajaran berhasil diperbarui.",
                Response::HTTP_OK,
                $this->toApiArray($record->fresh(['jamMulai', 'jamSelesai', 'pengampuMapel']))
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $record = JadwalPelajaran::find($id);
            if (!$record) {
                return $this->response("Jadwal pelajaran dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $record->delete();

            return $this->response("Jadwal pelajaran berhasil dihapus.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET aktif untuk satu pengampu_mapel
    public function getByPengampu(Request $request, $pengampuId)
    {
        try {
            $records = JadwalPelajaran::with(['jamMulai', 'jamSelesai', 'pengampuMapel'])
                ->where('pengampu_mapel_id', $pengampuId)
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Jadwal pengampu id:{$pengampuId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET aktif seluruh kelas — filter opsional: tahun_ajaran, semester
    public function getByKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $records = JadwalPelajaran::with(['jamMulai', 'jamSelesai', 'pengampuMapel'])
                ->whereHas('pengampuMapel', function ($q) use ($kelasId, $request) {
                    $q->where('kelas_id', $kelasId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Jadwal kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET aktif seluruh jadwal guru — filter opsional: tahun_ajaran, semester
    public function getByGuru(Request $request, $guruId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $records = JadwalPelajaran::with(['jamMulai', 'jamSelesai', 'pengampuMapel'])
                ->whereHas('pengampuMapel', function ($q) use ($guruId, $request) {
                    $q->where('guru_id', $guruId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Jadwal guru id:{$guruId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET riwayat lengkap (termasuk soft-deleted) per pengampu
    public function getRiwayatByPengampu(Request $request, $pengampuId)
    {
        try {
            $records = JadwalPelajaran::withTrashed()
                ->with(['jamMulai', 'jamSelesai', 'pengampuMapel' => fn($q) => $q->withTrashed()])
                ->where('pengampu_mapel_id', $pengampuId)
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Riwayat jadwal pengampu id:{$pengampuId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET riwayat lengkap per kelas
    public function getRiwayatByKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $records = JadwalPelajaran::withTrashed()
                ->with(['jamMulai', 'jamSelesai', 'pengampuMapel' => fn($q) => $q->withTrashed()])
                ->whereHas('pengampuMapel', function ($q) use ($kelasId, $request) {
                    $q->withTrashed()->where('kelas_id', $kelasId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Riwayat jadwal kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET riwayat lengkap per guru
    public function getRiwayatByGuru(Request $request, $guruId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $records = JadwalPelajaran::withTrashed()
                ->with(['jamMulai', 'jamSelesai', 'pengampuMapel' => fn($q) => $q->withTrashed()])
                ->whereHas('pengampuMapel', function ($q) use ($guruId, $request) {
                    $q->withTrashed()->where('guru_id', $guruId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('jam_mulai_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Riwayat jadwal guru id:{$guruId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Cek bentrok: apakah kelas sudah punya jadwal lain di hari + jam yang overlap
    private function hasKelasConflict(int $kelasId, string $tahunAjaran, string $semester, string $hari, int $mulaiKe, int $selesaiKe, ?int $excludeId = null): bool
    {
        $query = DB::table('jadwal_pelajaran as jp')
            ->join('pengampu_mapels as pm', 'jp.pengampu_mapel_id', '=', 'pm.id')
            ->join('jam_pelajaran as jm', 'jp.jam_mulai_id', '=', 'jm.id')
            ->join('jam_pelajaran as js', 'jp.jam_selesai_id', '=', 'js.id')
            ->whereNull('jp.deleted_at')
            ->whereNull('pm.deleted_at')
            ->where('pm.kelas_id', $kelasId)
            ->where('pm.tahun_ajaran', $tahunAjaran)
            ->where('pm.semester', $semester)
            ->where('jp.hari', $hari)
            ->where('jm.ke', '<=', $selesaiKe)
            ->where('js.ke', '>=', $mulaiKe);

        if ($excludeId !== null) {
            $query->where('jp.id', '!=', $excludeId);
        }

        return $query->exists();
    }

    // Cek bentrok: apakah guru sudah mengajar di tempat lain di hari + jam yang overlap
    private function hasGuruConflict(int $guruId, string $tahunAjaran, string $semester, string $hari, int $mulaiKe, int $selesaiKe, ?int $excludeId = null): bool
    {
        $query = DB::table('jadwal_pelajaran as jp')
            ->join('pengampu_mapels as pm', 'jp.pengampu_mapel_id', '=', 'pm.id')
            ->join('jam_pelajaran as jm', 'jp.jam_mulai_id', '=', 'jm.id')
            ->join('jam_pelajaran as js', 'jp.jam_selesai_id', '=', 'js.id')
            ->whereNull('jp.deleted_at')
            ->whereNull('pm.deleted_at')
            ->where('pm.guru_id', $guruId)
            ->where('pm.tahun_ajaran', $tahunAjaran)
            ->where('pm.semester', $semester)
            ->where('jp.hari', $hari)
            ->where('jm.ke', '<=', $selesaiKe)
            ->where('js.ke', '>=', $mulaiKe);

        if ($excludeId !== null) {
            $query->where('jp.id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toApiArray($record): array
    {
        $jm = $record->relationLoaded('jamMulai')    ? $record->jamMulai    : null;
        $js = $record->relationLoaded('jamSelesai')  ? $record->jamSelesai  : null;
        $pm = $record->relationLoaded('pengampuMapel') ? $record->pengampuMapel : null;

        return array_filter([
            'idJadwal'        => $record->id,
            'pengampuMapelId' => $record->pengampu_mapel_id,
            'guruId'          => $pm?->guru_id,
            'mapelId'         => $pm?->mapel_id,
            'kelasId'         => $pm?->kelas_id,
            'tahunAjaran'     => $pm?->tahun_ajaran,
            'semester'        => $pm?->semester,
            'hari'            => $record->hari,
            'jamMulaiId'      => $record->jam_mulai_id,
            'jamSelesaiId'    => $record->jam_selesai_id,
            'keMulai'         => $jm?->ke,
            'keSelesai'       => $js?->ke,
            'pukul'           => ($jm && $js) ? ($jm->jam_mulai . ' - ' . $js->jam_selesai) : null,
            'ruangan'         => $record->ruangan,
            'catatan'         => $record->catatan,
            'deletedAt'       => $record->deleted_at?->toDateTimeString(),
        ], fn($v) => $v !== null);
    }
}
