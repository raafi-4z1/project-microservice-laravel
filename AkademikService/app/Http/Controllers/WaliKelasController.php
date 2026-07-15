<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\WaliKelas;
use App\Traits\ApiResponser;

class WaliKelasController extends Controller
{
    use ApiResponser;

    // POST /akademik/wali — tetapkan wali kelas
    public function assign(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'guru_id'      => 'required|integer|min:1',
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

            // Satu kelas hanya boleh satu wali per semester.
            // withTrashed() agar soft-deleted tidak memicu error unique constraint.
            $existing = WaliKelas::withTrashed()
                ->where('kelas_id', $request->kelas_id)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->first();

            if ($existing) {
                if (!$existing->trashed()) {
                    return $this->response(
                        "Kelas ini sudah memiliki wali pada semester {$request->semester} tahun ajaran {$request->tahun_ajaran}. Gunakan ganti wali.",
                        Response::HTTP_CONFLICT
                    );
                }
                $existing->restore();
                $existing->update(['guru_id' => $request->guru_id]);
                return $this->response(
                    "Wali kelas berhasil ditetapkan.",
                    Response::HTTP_CREATED,
                    $this->toApiArray($existing->fresh()->toArray())
                );
            }

            $record = WaliKelas::create([
                'guru_id'      => $request->guru_id,
                'kelas_id'     => $request->kelas_id,
                'tahun_ajaran' => $request->tahun_ajaran,
                'semester'     => $request->semester,
            ]);

            return $this->response(
                "Wali kelas berhasil ditetapkan.",
                Response::HTTP_CREATED,
                $this->toApiArray($record->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/wali/{id} — ganti guru wali dalam semester yang sama
    public function gantiWali(Request $request, $id)
    {
        try {
            $record = WaliKelas::find($id);
            if (!$record) {
                return $this->response("Data wali kelas dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'guru_id' => 'required|integer|min:1',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            if ((int) $request->guru_id === (int) $record->guru_id) {
                return $this->response("Guru wali sudah sama, tidak ada perubahan.", Response::HTTP_CONFLICT);
            }

            $record->update(['guru_id' => $request->guru_id]);

            return $this->response(
                "Wali kelas berhasil diganti.",
                Response::HTTP_OK,
                $this->toApiArray($record->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/wali/{id} — batalkan penugasan wali (soft delete)
    public function remove(Request $request, $id)
    {
        try {
            $record = WaliKelas::find($id);
            if (!$record) {
                return $this->response("Data wali kelas dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $record->delete();

            return $this->response("Penugasan wali kelas berhasil dihapus.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/kelas/{kelas_id}/wali — wali aktif satu kelas
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

            $query = WaliKelas::where('kelas_id', $kelasId);
            if ($request->filled('tahun_ajaran')) $query->where('tahun_ajaran', $request->tahun_ajaran);
            if ($request->filled('semester'))     $query->where('semester', $request->semester);

            $records = $query->orderByDesc('created_at')->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Wali kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/guru/{guru_id}/wali — kelas yang diwali seorang guru
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

            $query = WaliKelas::where('guru_id', $guruId);
            if ($request->filled('tahun_ajaran')) $query->where('tahun_ajaran', $request->tahun_ajaran);
            if ($request->filled('semester'))     $query->where('semester', $request->semester);

            $records = $query->get()->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Kelas yang diwali guru id:{$guruId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        return array_filter([
            'idWaliKelas' => $data['id']           ?? null,
            'guruId'      => $data['guru_id']       ?? null,
            'kelasId'     => $data['kelas_id']      ?? null,
            'tahunAjaran' => $data['tahun_ajaran']  ?? null,
            'semester'    => $data['semester']      ?? null,
            'deletedAt'   => $data['deleted_at']    ?? null,
        ], fn($v) => $v !== null);
    }
}
