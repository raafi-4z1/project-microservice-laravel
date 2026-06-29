<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\SiswaKelas;
use App\Traits\ApiResponser;

class SiswaKelasController extends Controller
{
    use ApiResponser;

    public function assign(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'siswa_id'    => 'required|integer|min:1',
                'kelas_id'    => 'required|integer|min:1',
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'    => 'required|in:1,2',
                'limit_siswa' => 'required|integer|min:1',
            ], [
                'tahun_ajaran.regex'  => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2024/2025.',
                'semester.in'         => 'Semester harus 1 atau 2.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            // Cek siswa sudah terdaftar di kelas lain pada semester ini.
            // withTrashed() diperlukan agar soft-deleted record tidak memicu SQL unique constraint error.
            $existing = SiswaKelas::withTrashed()
                ->where('siswa_id', $request->siswa_id)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->first();

            if ($existing) {
                if (!$existing->trashed()) {
                    return $this->response(
                        "Siswa sudah terdaftar di kelas lain pada semester {$request->semester} tahun ajaran {$request->tahun_ajaran}.",
                        Response::HTTP_CONFLICT
                    );
                }
                // Record pernah dihapus: cek kapasitas kelas tujuan, lalu restore dengan kelas baru
                $jumlahSiswa = SiswaKelas::where('kelas_id', $request->kelas_id)
                    ->where('tahun_ajaran', $request->tahun_ajaran)
                    ->where('semester', $request->semester)
                    ->count();
                if ($jumlahSiswa >= $request->limit_siswa) {
                    return $this->response(
                        "Kelas sudah penuh. Kapasitas maksimal: {$request->limit_siswa} siswa.",
                        Response::HTTP_CONFLICT
                    );
                }
                $existing->restore();
                $existing->update(['kelas_id' => $request->kelas_id]);
                return $this->response(
                    "Siswa berhasil ditambahkan ke kelas.",
                    Response::HTTP_CREATED,
                    $this->toApiArray($existing->fresh()->toArray())
                );
            }

            // Cek kapasitas kelas
            $jumlahSiswa = SiswaKelas::where('kelas_id', $request->kelas_id)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->count();

            if ($jumlahSiswa >= $request->limit_siswa) {
                return $this->response(
                    "Kelas sudah penuh. Kapasitas maksimal: {$request->limit_siswa} siswa.",
                    Response::HTTP_CONFLICT
                );
            }

            $record = SiswaKelas::create([
                'siswa_id'     => $request->siswa_id,
                'kelas_id'     => $request->kelas_id,
                'tahun_ajaran' => $request->tahun_ajaran,
                'semester'     => $request->semester,
            ]);

            return $this->response(
                "Siswa berhasil ditambahkan ke kelas.",
                Response::HTTP_CREATED,
                $this->toApiArray($record->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Pindah kelas dalam semester yang sama — record di-update, tidak di-hapus
    public function pindahKelas(Request $request, $id)
    {
        try {
            $record = SiswaKelas::find($id);
            if (!$record) {
                return $this->response("Data pembagian kelas dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'kelas_id'    => 'required|integer|min:1',
                'limit_siswa' => 'required|integer|min:1',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            if ((int) $request->kelas_id === (int) $record->kelas_id) {
                return $this->response("Siswa sudah berada di kelas yang sama.", Response::HTTP_CONFLICT);
            }

            $jumlahSiswa = SiswaKelas::where('kelas_id', $request->kelas_id)
                ->where('tahun_ajaran', $record->tahun_ajaran)
                ->where('semester', $record->semester)
                ->count();

            if ($jumlahSiswa >= $request->limit_siswa) {
                return $this->response(
                    "Kelas tujuan sudah penuh. Kapasitas maksimal: {$request->limit_siswa} siswa.",
                    Response::HTTP_CONFLICT
                );
            }

            $record->update(['kelas_id' => $request->kelas_id]);

            return $this->response(
                "Siswa berhasil dipindah ke kelas id:{$request->kelas_id}.",
                Response::HTTP_OK,
                $this->toApiArray($record->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Batalkan pembagian kelas — data disimpan (soft delete) untuk keperluan pencatatan
    public function removeSiswa(Request $request, $id)
    {
        try {
            $record = SiswaKelas::find($id);
            if (!$record) {
                return $this->response("Data pembagian kelas dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $record->delete();

            return $this->response("Siswa berhasil dikeluarkan dari kelas.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSiswaByKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = SiswaKelas::where('kelas_id', $kelasId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Daftar siswa di kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getKelasBySiswa(Request $request, $siswaId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = SiswaKelas::where('siswa_id', $siswaId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat kelas siswa id:{$siswaId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Riwayat lengkap termasuk data yang sudah dibatalkan (soft-deleted)
    public function getRiwayatSiswa(Request $request, $siswaId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = SiswaKelas::withTrashed()->where('siswa_id', $siswaId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->orderBy('created_at')->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat lengkap kelas siswa id:{$siswaId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRiwayatKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = SiswaKelas::withTrashed()->where('kelas_id', $kelasId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->orderBy('created_at')->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat lengkap siswa di kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Kembalikan daftar siswa_id yang sudah terdaftar di kelas untuk semester tertentu.
    // Dipanggil dari Gateway saat menghitung siswa yang belum terdaftar (cross-service diff).
    public function getSiswaTerdaftar(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
            ], [
                'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2024/2025.',
                'semester.in'        => 'Semester harus 1 atau 2.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $siswaIds = SiswaKelas::where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->pluck('siswa_id')
                ->unique()
                ->values();

            return $this->response(
                "Siswa terdaftar semester {$request->semester} tahun ajaran {$request->tahun_ajaran}.",
                Response::HTTP_OK,
                $siswaIds
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        return array_filter([
            'idSiswaKelas' => $data['id']          ?? null,
            'siswaId'      => $data['siswa_id']     ?? null,
            'kelasId'      => $data['kelas_id']     ?? null,
            'tahunAjaran'  => $data['tahun_ajaran'] ?? null,
            'semester'     => $data['semester']     ?? null,
            'deletedAt'    => $data['deleted_at']   ?? null,
        ], fn($v) => $v !== null);
    }
}
