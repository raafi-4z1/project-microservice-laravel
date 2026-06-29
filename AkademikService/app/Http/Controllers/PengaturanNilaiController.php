<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\PengaturanNilai;
use App\Traits\ApiResponser;

class PengaturanNilaiController extends Controller
{
    use ApiResponser;

    // GET /pengaturan-nilai?tahun_ajaran=&semester=
    public function index(Request $request)
    {
        try {
            $query = PengaturanNilai::query();
            if ($request->filled('tahun_ajaran')) $query->where('tahun_ajaran', $request->tahun_ajaran);
            if ($request->filled('semester'))     $query->where('semester', $request->semester);

            $records = $query->orderBy('tahun_ajaran')->orderBy('semester')
                ->get()->map(fn($r) => $this->toApiArray($r));

            return $this->response("Daftar pengaturan nilai.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /pengaturan-nilai
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
                'bobot_harian' => 'required|integer|min:1|max:98',
                'bobot_uts'    => 'required|integer|min:1|max:98',
                'bobot_uas'    => 'required|integer|min:1|max:98',
            ], [
                'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY.',
                'semester.in'        => 'Semester harus 1 atau 2.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $total = $request->bobot_harian + $request->bobot_uts + $request->bobot_uas;
            if ($total !== 100) {
                return $this->response("Total bobot harus 100 (saat ini: {$total}).", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $existing = PengaturanNilai::where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)->first();
            if ($existing) {
                return $this->response(
                    "Pengaturan nilai untuk semester {$request->semester} tahun ajaran {$request->tahun_ajaran} sudah ada. Gunakan PATCH untuk mengubah.",
                    Response::HTTP_CONFLICT
                );
            }

            $record = PengaturanNilai::create($request->only(['tahun_ajaran', 'semester', 'bobot_harian', 'bobot_uts', 'bobot_uas']));

            return $this->response("Pengaturan nilai berhasil disimpan.", Response::HTTP_CREATED, $this->toApiArray($record));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /pengaturan-nilai/{id}
    public function update(Request $request, $id)
    {
        try {
            $record = PengaturanNilai::find($id);
            if (!$record) {
                return $this->response("Pengaturan nilai dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'bobot_harian' => 'sometimes|integer|min:1|max:98',
                'bobot_uts'    => 'sometimes|integer|min:1|max:98',
                'bobot_uas'    => 'sometimes|integer|min:1|max:98',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $bobotHarian = $request->input('bobot_harian', $record->bobot_harian);
            $bobotUts    = $request->input('bobot_uts',    $record->bobot_uts);
            $bobotUas    = $request->input('bobot_uas',    $record->bobot_uas);
            $total = $bobotHarian + $bobotUts + $bobotUas;

            if ($total !== 100) {
                return $this->response("Total bobot harus 100 (saat ini: {$total}).", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $record->update(['bobot_harian' => $bobotHarian, 'bobot_uts' => $bobotUts, 'bobot_uas' => $bobotUas]);

            return $this->response("Pengaturan nilai berhasil diperbarui.", Response::HTTP_OK, $this->toApiArray($record->fresh()));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray($record): array
    {
        return [
            'idPengaturan' => $record->id,
            'tahunAjaran'  => $record->tahun_ajaran,
            'semester'     => (int) $record->semester,
            'bobotHarian'  => (int) $record->bobot_harian,
            'bobotUts'     => (int) $record->bobot_uts,
            'bobotUas'     => (int) $record->bobot_uas,
        ];
    }
}
