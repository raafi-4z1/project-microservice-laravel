<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\PengampuMapel;
use App\Traits\ApiResponser;

class PengampuMapelController extends Controller
{
    use ApiResponser;

    public function assign(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'guru_id'      => 'required|integer|min:1',
                'mapel_id'     => 'required|integer|min:1',
                'kelas_id'     => 'required|integer|min:1',
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
            ], [
                'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2024/2025.',
                'semester.in'        => 'Semester harus 1 atau 2.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            // Cek satu mapel di satu kelas hanya boleh satu pengampu per semester
            $existing = PengampuMapel::where('mapel_id', $request->mapel_id)
                ->where('kelas_id', $request->kelas_id)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->first();

            if ($existing) {
                return $this->response(
                    "Mapel ini di kelas yang sama sudah memiliki pengampu pada semester {$request->semester} tahun ajaran {$request->tahun_ajaran}.",
                    Response::HTTP_CONFLICT
                );
            }

            $record = PengampuMapel::create([
                'guru_id'      => $request->guru_id,
                'mapel_id'     => $request->mapel_id,
                'kelas_id'     => $request->kelas_id,
                'tahun_ajaran' => $request->tahun_ajaran,
                'semester'     => $request->semester,
            ]);

            return $this->response(
                "Guru berhasil ditetapkan sebagai pengampu mapel.",
                Response::HTTP_CREATED,
                $this->toApiArray($record->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Ganti guru pengampu dalam semester yang sama — record di-update, tidak di-hapus
    public function gantiGuru(Request $request, $id)
    {
        try {
            $record = PengampuMapel::find($id);
            if (!$record) {
                return $this->response("Data pengampu mapel dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'guru_id' => 'required|integer|min:1',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            if ((int) $request->guru_id === (int) $record->guru_id) {
                return $this->response("Guru pengampu sudah sama, tidak ada perubahan.", Response::HTTP_CONFLICT);
            }

            $record->update(['guru_id' => $request->guru_id]);

            return $this->response(
                "Guru pengampu berhasil diganti.",
                Response::HTTP_OK,
                $this->toApiArray($record->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Batalkan penugasan — data disimpan (soft delete) untuk keperluan pencatatan
    public function removeGuru(Request $request, $id)
    {
        try {
            $record = PengampuMapel::find($id);
            if (!$record) {
                return $this->response("Data pengampu mapel dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $record->delete();

            return $this->response("Penugasan pengampu mapel berhasil dihapus.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMapelByGuru(Request $request, $guruId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = PengampuMapel::where('guru_id', $guruId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Daftar mapel yang diampu guru id:{$guruId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getGuruByMapel(Request $request, $mapelId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'kelas_id'     => 'nullable|integer|min:1',
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = PengampuMapel::where('mapel_id', $mapelId);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }
            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Daftar pengampu mapel id:{$mapelId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Riwayat lengkap termasuk data yang sudah dibatalkan (soft-deleted)
    public function getRiwayatGuru(Request $request, $guruId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = PengampuMapel::withTrashed()->where('guru_id', $guruId);

            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->orderBy('created_at')->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat lengkap pengampu guru id:{$guruId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRiwayatMapel(Request $request, $mapelId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'kelas_id'     => 'nullable|integer|min:1',
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $query = PengampuMapel::withTrashed()->where('mapel_id', $mapelId);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }
            if ($request->filled('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            $records = $query->orderBy('created_at')->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat lengkap guru pengampu mapel id:{$mapelId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        return array_filter([
            'idPengampuMapel' => $data['id']          ?? null,
            'guruId'          => $data['guru_id']      ?? null,
            'mapelId'         => $data['mapel_id']     ?? null,
            'kelasId'         => $data['kelas_id']     ?? null,
            'tahunAjaran'     => $data['tahun_ajaran'] ?? null,
            'semester'        => $data['semester']     ?? null,
            'deletedAt'       => $data['deleted_at']   ?? null,
        ], fn($v) => $v !== null);
    }
}
