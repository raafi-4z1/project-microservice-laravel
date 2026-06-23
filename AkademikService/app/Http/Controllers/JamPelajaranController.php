<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\JamPelajaran;
use App\Models\JadwalPelajaran;
use App\Traits\ApiResponser;

class JamPelajaranController extends Controller
{
    use ApiResponser;

    public function index()
    {
        try {
            $records = JamPelajaran::orderBy('ke')->get()
                ->map(fn($r) => $this->toApiArray($r->toArray()));

            return $this->response("Daftar jam pelajaran.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'ke'          => 'required|integer|min:1|max:10|unique:jam_pelajaran,ke',
                'jam_mulai'   => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            ], [
                'ke.unique'         => 'Jam ke-:input sudah terdaftar.',
                'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $record = JamPelajaran::create($request->only(['ke', 'jam_mulai', 'jam_selesai']));

            return $this->response(
                "Jam ke-{$record->ke} berhasil ditambahkan.",
                Response::HTTP_CREATED,
                $this->toApiArray($record->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $record = JamPelajaran::find($id);
            if (!$record) {
                return $this->response("Jam pelajaran dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'ke'          => "integer|min:1|max:10|unique:jam_pelajaran,ke,{$id}",
                'jam_mulai'   => 'date_format:H:i',
                'jam_selesai' => 'date_format:H:i',
            ], [
                'ke.unique' => 'Jam ke-:input sudah terdaftar.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $jamMulai   = $request->input('jam_mulai', $record->jam_mulai);
            $jamSelesai = $request->input('jam_selesai', $record->jam_selesai);
            if (strtotime($jamSelesai) <= strtotime($jamMulai)) {
                return $this->response('Jam selesai harus setelah jam mulai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $updateData = [];
            if ($request->filled('ke'))          $updateData['ke']          = $request->ke;
            if ($request->filled('jam_mulai'))   $updateData['jam_mulai']   = $request->jam_mulai;
            if ($request->filled('jam_selesai')) $updateData['jam_selesai'] = $request->jam_selesai;

            if (empty($updateData)) {
                return $this->response('Tidak ada field yang diubah.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $record->update($updateData);

            return $this->response(
                "Jam ke-{$record->fresh()->ke} berhasil diperbarui.",
                Response::HTTP_OK,
                $this->toApiArray($record->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $record = JamPelajaran::find($id);
            if (!$record) {
                return $this->response("Jam pelajaran dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            // Cek termasuk riwayat (soft-deleted) agar FK historis tidak rusak
            $dipakai = JadwalPelajaran::withTrashed()
                ->where(fn($q) => $q->where('jam_mulai_id', $id)->orWhere('jam_selesai_id', $id))
                ->exists();

            if ($dipakai) {
                return $this->response(
                    "Jam ke-{$record->ke} masih digunakan oleh jadwal. Hapus jadwal terkait terlebih dahulu.",
                    Response::HTTP_CONFLICT
                );
            }

            $ke = $record->ke;
            $record->delete();

            return $this->response("Jam ke-{$ke} berhasil dihapus.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        return [
            'idJam'      => $data['id']          ?? null,
            'ke'         => $data['ke']           ?? null,
            'jamMulai'   => $data['jam_mulai']    ?? null,
            'jamSelesai' => $data['jam_selesai']  ?? null,
        ];
    }
}
