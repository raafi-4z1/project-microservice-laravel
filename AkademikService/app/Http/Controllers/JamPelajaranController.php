<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\JamPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\PeriodeKhusus;
use App\Traits\ApiResponser;

/**
 * Jam pelajaran = pemetaan slot (`ke`) -> jam dinding.
 * Bisa bervariasi per periode (mis. Ramadan) dan per hari (mis. Jumat).
 * `ke` TIDAK lagi unique global — keunikan (periode_id, hari, ke) dijaga di sini
 * karena unique MySQL menganggap NULL saling berbeda.
 */
class JamPelajaranController extends Controller
{
    use ApiResponser;

    private const TZ = 'Asia/Jakarta';

    /**
     * GET /akademik/jam
     *  - ?tanggal=YYYY-MM-DD [&hari=] -> set jam EFEKTIF pada tanggal itu
     *  - ?periode_id= / ?hari=        -> filter mentah
     *  - tanpa param                  -> semua baris
     */
    public function index(Request $request)
    {
        try {
            // Mode resolusi: kembalikan set yang benar-benar berlaku pada tanggal
            if ($request->filled('tanggal')) {
                $tanggal = $request->input('tanggal');
                $periode = PeriodeKhusus::untukTanggal($tanggal);
                $hari    = $request->input('hari') ?: $this->hariIndo(Carbon::parse($tanggal, self::TZ));

                $records = JamPelajaran::setEfektif($periode?->id, $hari)
                    ->map(fn($r) => $this->toApiArray($r->toArray()));

                return $this->response("Jam efektif {$tanggal} ({$hari}).", Response::HTTP_OK, [
                    'tanggal' => $tanggal,
                    'hari'    => $hari,
                    'periode' => $periode ? ['idPeriode' => $periode->id, 'nama' => $periode->nama, 'jenis' => $periode->jenis] : null,
                    'jam'     => $records->values(),
                ]);
            }

            $query = JamPelajaran::query();
            if ($request->has('periode_id')) {
                $request->input('periode_id') === null || $request->input('periode_id') === ''
                    ? $query->whereNull('periode_id')
                    : $query->where('periode_id', $request->input('periode_id'));
            }
            if ($request->filled('hari')) $query->where('hari', $request->hari);

            $records = $query->orderBy('periode_id')->orderBy('ke')->get()
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
                'ke'          => 'required|integer|min:1|max:10',
                'jam_mulai'   => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
                'periode_id'  => 'nullable|integer|exists:periode_khusus,id',
                'hari'        => 'nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            ], [
                'jam_selesai.after'  => 'Jam selesai harus setelah jam mulai.',
                'periode_id.exists'  => 'Periode khusus tidak ditemukan.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $periodeId = $request->input('periode_id');
            $hari      = $request->input('hari');

            if ($this->sudahAda($periodeId, $hari, (int) $request->ke)) {
                return $this->response(
                    "Jam ke-{$request->ke} sudah terdaftar untuk " . $this->labelSet($periodeId, $hari) . '.',
                    Response::HTTP_CONFLICT
                );
            }

            $record = JamPelajaran::create([
                'ke'          => $request->ke,
                'jam_mulai'   => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'periode_id'  => $periodeId,
                'hari'        => $hari,
            ]);

            return $this->response(
                "Jam ke-{$record->ke} berhasil ditambahkan (" . $this->labelSet($periodeId, $hari) . ').',
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
                'ke'          => 'sometimes|integer|min:1|max:10',
                'jam_mulai'   => 'sometimes|date_format:H:i',
                'jam_selesai' => 'sometimes|date_format:H:i',
                'hari'        => 'sometimes|nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $jamMulai   = $request->input('jam_mulai', $record->jam_mulai);
            $jamSelesai = $request->input('jam_selesai', $record->jam_selesai);
            if (strtotime($jamSelesai) <= strtotime($jamMulai)) {
                return $this->response('Jam selesai harus setelah jam mulai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $ke   = $request->has('ke')   ? (int) $request->ke : $record->ke;
            $hari = $request->has('hari') ? $request->hari     : $record->hari;

            if (($ke !== $record->ke || $hari !== $record->hari)
                && $this->sudahAda($record->periode_id, $hari, $ke, (int) $id)) {
                return $this->response(
                    "Jam ke-{$ke} sudah terdaftar untuk " . $this->labelSet($record->periode_id, $hari) . '.',
                    Response::HTTP_CONFLICT
                );
            }

            $updateData = [];
            if ($request->has('ke'))             $updateData['ke']          = $ke;
            if ($request->filled('jam_mulai'))   $updateData['jam_mulai']   = $request->jam_mulai;
            if ($request->filled('jam_selesai')) $updateData['jam_selesai'] = $request->jam_selesai;
            if ($request->has('hari'))           $updateData['hari']        = $hari;

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

            // Hanya baris set NORMAL yang dirujuk FK jadwal; baris periode aman dihapus.
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

    // Duplikat (periode_id, hari, ke) — null-aware, tidak bisa diserahkan ke unique MySQL
    private function sudahAda(?int $periodeId, ?string $hari, int $ke, ?int $excludeId = null): bool
    {
        $q = JamPelajaran::where('ke', $ke)
            ->where(fn($x) => $periodeId === null ? $x->whereNull('periode_id') : $x->where('periode_id', $periodeId))
            ->where(fn($x) => $hari === null ? $x->whereNull('hari') : $x->where('hari', $hari));

        if ($excludeId !== null) $q->where('id', '!=', $excludeId);

        return $q->exists();
    }

    private function labelSet(?int $periodeId, ?string $hari): string
    {
        $set  = $periodeId === null ? 'set normal' : "periode id:{$periodeId}";
        $hari = $hari === null ? 'semua hari' : $hari;
        return "{$set}, {$hari}";
    }

    private function hariIndo(Carbon $d): string
    {
        return [
            Carbon::SUNDAY => 'Minggu', Carbon::MONDAY => 'Senin', Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu', Carbon::THURSDAY => 'Kamis', Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
        ][$d->dayOfWeek];
    }

    private function toApiArray(array $data): array
    {
        return [
            'idJam'      => $data['id']          ?? null,
            'periodeId'  => $data['periode_id']  ?? null,
            'hari'       => $data['hari']        ?? null,
            'ke'         => $data['ke']          ?? null,
            'jamMulai'   => $data['jam_mulai']   ?? null,
            'jamSelesai' => $data['jam_selesai'] ?? null,
        ];
    }
}
