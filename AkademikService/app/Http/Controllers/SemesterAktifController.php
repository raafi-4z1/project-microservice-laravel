<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\SemesterAktif;
use App\Traits\ApiResponser;

class SemesterAktifController extends Controller
{
    use ApiResponser;

    // GET — semester yang sedang aktif
    public function getAktif()
    {
        try {
            $semester = SemesterAktif::where('is_aktif', true)->first();

            if (!$semester) {
                return $this->response("Belum ada semester aktif yang ditetapkan.", Response::HTTP_NOT_FOUND);
            }

            return $this->response("Semester aktif.", Response::HTTP_OK, $this->toApiArray($semester->toArray()));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST — tetapkan semester aktif baru (menutup semester sebelumnya)
    public function setAktif(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran'   => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'       => 'required|in:1,2',
                'tanggal_mulai'  => 'required|date',
                'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            ], [
                'tahun_ajaran.regex'        => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2024/2025.',
                'semester.in'               => 'Semester harus 1 atau 2.',
                'tanggal_selesai.after'     => 'Tanggal selesai harus setelah tanggal mulai.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            DB::transaction(function () use ($request) {
                // Tutup semester sebelumnya yang masih aktif
                SemesterAktif::where('is_aktif', true)->update(['is_aktif' => false]);

                SemesterAktif::create([
                    'tahun_ajaran'    => $request->tahun_ajaran,
                    'semester'        => $request->semester,
                    'tanggal_mulai'   => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                    'is_aktif'        => true,
                ]);
            });

            $created = SemesterAktif::where('is_aktif', true)->first();

            return $this->response(
                "Semester {$request->semester} tahun ajaran {$request->tahun_ajaran} ditetapkan sebagai aktif.",
                Response::HTTP_CREATED,
                $this->toApiArray($created->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET — semua riwayat semester yang pernah aktif
    public function getRiwayat()
    {
        try {
            $records = SemesterAktif::orderByDesc('tanggal_mulai')->get()
                ->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Riwayat semester.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        return [
            'idSemesterAktif' => $data['id']               ?? null,
            'tahunAjaran'     => $data['tahun_ajaran']      ?? null,
            'semester'        => $data['semester']          ?? null,
            'tanggalMulai'    => $data['tanggal_mulai']     ?? null,
            'tanggalSelesai'  => $data['tanggal_selesai']   ?? null,
            'isAktif'         => (bool) ($data['is_aktif']  ?? false),
        ];
    }
}
