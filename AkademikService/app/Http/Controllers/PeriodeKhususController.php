<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\PeriodeKhusus;
use App\Traits\ApiResponser;

/**
 * Periode khusus: rentang tanggal yang mengubah aturan akademik sementara
 * (Ramadan, pekan ujian, libur, kegiatan khusus). Lihat migration untuk
 * aturan resolusi (rentang terpendek menang).
 */
class PeriodeKhususController extends Controller
{
    use ApiResponser;

    private const TZ = 'Asia/Jakarta';

    // POST /akademik/periode
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'nama'           => 'required|string|max:100',
                'tahun_ajaran'   => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'       => 'required|in:1,2',
                'jenis'          => 'required|in:ramadan,ujian,libur,khusus',
                'berlaku_dari'   => 'required|date',
                'berlaku_sampai' => 'required|date|after_or_equal:berlaku_dari',
                'kbm_normal'     => 'nullable|boolean',
                'keterangan'     => 'nullable|string|max:255',
            ], [
                'tahun_ajaran.regex'            => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2024/2025.',
                'berlaku_sampai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $record = PeriodeKhusus::create([
                'nama'           => $request->nama,
                'tahun_ajaran'   => $request->tahun_ajaran,
                'semester'       => $request->semester,
                'jenis'          => $request->jenis,
                'berlaku_dari'   => $request->berlaku_dari,
                'berlaku_sampai' => $request->berlaku_sampai,
                'kbm_normal'     => $request->has('kbm_normal')
                    ? (bool) $request->kbm_normal
                    : $this->kbmDefault($request->jenis),
                'keterangan'     => $request->keterangan,
            ]);

            return $this->response('Periode khusus berhasil dibuat.', Response::HTTP_CREATED, $this->toApiArray($record));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/periode/{id}
    public function update(Request $request, $id)
    {
        try {
            $record = PeriodeKhusus::find($id);
            if (!$record) {
                return $this->response("Periode khusus id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), [
                'nama'           => 'sometimes|string|max:100',
                'jenis'          => 'sometimes|in:ramadan,ujian,libur,khusus',
                'berlaku_dari'   => 'sometimes|date',
                'berlaku_sampai' => 'sometimes|date',
                'kbm_normal'     => 'sometimes|boolean',
                'keterangan'     => 'nullable|string|max:255',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $dari   = $request->input('berlaku_dari', $record->berlaku_dari->toDateString());
            $sampai = $request->input('berlaku_sampai', $record->berlaku_sampai->toDateString());
            if (Carbon::parse($sampai)->lt(Carbon::parse($dari))) {
                return $this->response('Tanggal selesai tidak boleh sebelum tanggal mulai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $data = $request->only(['nama', 'jenis', 'berlaku_dari', 'berlaku_sampai', 'kbm_normal', 'keterangan']);
            if (empty($data)) {
                return $this->response('Tidak ada field yang diubah.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $record->update($data);

            return $this->response('Periode khusus berhasil diperbarui.', Response::HTTP_OK, $this->toApiArray($record->fresh()));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/periode/{id}
    public function destroy($id)
    {
        try {
            $record = PeriodeKhusus::find($id);
            if (!$record) {
                return $this->response("Periode khusus id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }
            $record->delete();

            return $this->response('Periode khusus berhasil dihapus.', Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/periode — filter: tahun_ajaran, semester, jenis
    public function index(Request $request)
    {
        try {
            $query = PeriodeKhusus::query();
            if ($request->filled('tahun_ajaran')) $query->where('tahun_ajaran', $request->tahun_ajaran);
            if ($request->filled('semester'))     $query->where('semester', $request->semester);
            if ($request->filled('jenis'))        $query->where('jenis', $request->jenis);

            $records = $query->orderBy('berlaku_dari')->get()->map(fn($r) => $this->toApiArray($r));

            return $this->response('Daftar periode khusus.', Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/periode/aktif?tanggal= — periode yang berlaku pada tanggal (default hari ini WIB)
    public function aktif(Request $request)
    {
        try {
            $tanggal = $request->input('tanggal', Carbon::now(self::TZ)->toDateString());
            $periode = PeriodeKhusus::untukTanggal($tanggal);

            if (!$periode) {
                return $this->response("Tidak ada periode khusus pada {$tanggal} (aturan normal berlaku).", Response::HTTP_OK, []);
            }

            return $this->response("Periode berlaku pada {$tanggal}.", Response::HTTP_OK, $this->toApiArray($periode));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Default KBM: ujian & libur menghentikan KBM normal
    private function kbmDefault(string $jenis): bool
    {
        return !in_array($jenis, ['ujian', 'libur'], true);
    }

    private function toApiArray(PeriodeKhusus $r): array
    {
        return [
            'idPeriode'     => $r->id,
            'nama'          => $r->nama,
            'tahunAjaran'   => $r->tahun_ajaran,
            'semester'      => $r->semester,
            'jenis'         => $r->jenis,
            'berlakuDari'   => $r->berlaku_dari?->toDateString(),
            'berlakuSampai' => $r->berlaku_sampai?->toDateString(),
            'kbmNormal'     => (bool) $r->kbm_normal,
            'keterangan'    => $r->keterangan,
        ];
    }
}
