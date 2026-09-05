<?php

namespace App\Http\Controllers;

use App\Models\Acara;
use App\Models\PeriodeKhusus;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AcaraController extends Controller
{
    use ApiResponser;

    /**
     * Periode yang TIDAK boleh ditimpa acara.
     *
     * Hanya `ujian`. Itu inti permintaannya: petugas acara tidak boleh menaruh
     * kegiatan di atas pekan ujian.
     *
     * `ramadan` disebut "opsional" di brief dan sengaja TIDAK dimasukkan.
     * Ramadan berlangsung sebulan penuh dan sekolah TETAP berjalan (jamnya saja
     * yang dipendekkan) — melindunginya berarti tak satu pun agenda bisa dibuat
     * selama sebulan, padahal justru di bulan itu acara menumpuk: pesantren
     * kilat, buka bersama, zakat. Terbukti langsung saat pengujian: satu periode
     * Ramadan menutup seluruh Februari.
     *
     * `libur` dan `khusus` juga tidak dilindungi — acara memang wajar berbarengan
     * dengan libur (mis. "Libur Kemerdekaan" + "Upacara 17 Agustus").
     *
     * Kalau suatu saat ramadan perlu ikut dilindungi, cukup tambahkan di sini.
     */
    private const PERIODE_DILINDUNGI = ['ujian'];

    public function index(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'dari'     => 'required|date_format:Y-m-d',
                'sampai'   => 'required|date_format:Y-m-d|after_or_equal:dari',
                'kategori' => 'sometimes|in:' . implode(',', Acara::KATEGORI),
            ], [
                'sampai.after_or_equal' => 'Parameter sampai harus >= dari.',
            ]);

            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $q = Acara::bersinggungan($request->dari, $request->sampai);
            if ($request->filled('kategori')) {
                $q->where('kategori', $request->kategori);
            }

            $rows = $q->orderBy('tanggal_mulai')->orderBy('jam_mulai')->orderBy('id')->get();

            // Rentang tanpa acara membalas [] (bukan 404) — bulan kosong di
            // kalender itu keadaan normal, bukan kesalahan.
            return $this->response(
                'Daftar acara.',
                Response::HTTP_OK,
                $rows->map(fn($a) => $this->toApiArray($a))->all()
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), $this->aturan(), $this->pesan());
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $seharian = filter_var($request->input('seharian', true), FILTER_VALIDATE_BOOLEAN);
            if (!$seharian && (!$request->filled('jam_mulai') || !$request->filled('jam_selesai'))) {
                return $this->response('jam_mulai dan jam_selesai wajib diisi bila seharian = false.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($bentrok = $this->cariBentrok($request->tanggal_mulai, $request->tanggal_selesai)) {
                return $bentrok;
            }

            $acara = Acara::create($this->payload($request));

            return $this->response('Acara berhasil dibuat.', Response::HTTP_CREATED, $this->toApiArray($acara));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $acara = Acara::find($id);
            if (!$acara) {
                return $this->response("Acara dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $validate = Validator::make($request->all(), $this->aturan(true), $this->pesan());
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            // PATCH parsial: konsistensi diperiksa terhadap nilai GABUNGAN (yang
            // dikirim + yang tersimpan). Kalau hanya yang dikirim yang diperiksa,
            // mengubah tanggal_mulai saja bisa melewati tanggal_selesai lama.
            $mulai      = $request->input('tanggal_mulai', $acara->tanggal_mulai->toDateString());
            $selesai    = $request->input('tanggal_selesai', $acara->tanggal_selesai->toDateString());
            $seharian   = $request->has('seharian')
                ? filter_var($request->input('seharian'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $acara->seharian;
            $jamMulai   = $request->input('jam_mulai', $acara->jam_mulai);
            $jamSelesai = $request->input('jam_selesai', $acara->jam_selesai);

            if ($selesai < $mulai) {
                return $this->response('tanggal_selesai harus >= tanggal_mulai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if (!$seharian && (!$jamMulai || !$jamSelesai)) {
                return $this->response('jam_mulai dan jam_selesai wajib diisi bila seharian = false.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($bentrok = $this->cariBentrok($mulai, $selesai)) {
                return $bentrok;
            }

            $acara->update($this->payload($request, $acara));

            return $this->response('Acara berhasil diperbarui.', Response::HTTP_OK, $this->toApiArray($acara->fresh()));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $acara = Acara::find($id);
            if (!$acara) {
                return $this->response("Acara dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $acara->delete();

            return $this->response("Acara dengan id:{$id} berhasil dihapus.", Response::HTTP_ACCEPTED, ['idAcara' => (int) $id]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Helper -----------------------------------------------------------------

    private function aturan(bool $parsial = false): array
    {
        $wajib = $parsial ? 'sometimes' : 'required';

        return [
            'judul'           => "{$wajib}|string|max:150",
            'kategori'        => "{$wajib}|in:" . implode(',', Acara::KATEGORI),
            'tanggal_mulai'   => "{$wajib}|date_format:Y-m-d",
            'tanggal_selesai' => "{$wajib}|date_format:Y-m-d" . ($parsial ? '' : '|after_or_equal:tanggal_mulai'),
            'seharian'        => 'sometimes|boolean',
            'jam_mulai'       => 'nullable|date_format:H:i',
            'jam_selesai'     => 'nullable|date_format:H:i|after:jam_mulai',
            'lokasi'          => 'nullable|string|max:100',
            'keterangan'      => 'nullable|string|max:255',
        ];
    }

    private function pesan(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'tanggal_selesai harus >= tanggal_mulai.',
            'jam_selesai.after'              => 'jam_selesai harus setelah jam_mulai.',
            'kategori.in'                    => 'kategori harus salah satu: ' . implode(', ', Acara::KATEGORI) . '.',
        ];
    }

    private function payload(Request $request, ?Acara $lama = null): array
    {
        $seharian = $request->has('seharian')
            ? filter_var($request->input('seharian'), FILTER_VALIDATE_BOOLEAN)
            : ($lama ? (bool) $lama->seharian : true);

        $data = [];
        foreach (['judul', 'kategori', 'tanggal_mulai', 'tanggal_selesai', 'lokasi', 'keterangan'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->input($f);
            }
        }

        $data['seharian'] = $seharian;

        // Acara seharian tidak boleh menyimpan jam sisa dari perubahan sebelumnya —
        // kalau tidak, klien menerima seharian=true berikut jamMulai lama.
        if ($seharian) {
            $data['jam_mulai']   = null;
            $data['jam_selesai'] = null;
        } else {
            if ($request->has('jam_mulai'))   { $data['jam_mulai']   = $request->input('jam_mulai'); }
            if ($request->has('jam_selesai')) { $data['jam_selesai'] = $request->input('jam_selesai'); }
        }

        return $data;
    }

    /**
     * Tolak acara yang bersinggungan dengan periode akademik terproteksi.
     *
     * Mengembalikan JsonResponse 422 bila bentrok, atau null bila aman. Detail
     * periode yang bentrok ikut di `data` supaya app bisa menampilkan periode
     * mana yang menghalangi, bukan hanya pesan buntu.
     */
    private function cariBentrok(string $mulai, string $selesai)
    {
        $bentrok = PeriodeKhusus::whereIn('jenis', self::PERIODE_DILINDUNGI)
            ->whereDate('berlaku_dari', '<=', $selesai)
            ->whereDate('berlaku_sampai', '>=', $mulai)
            ->orderBy('berlaku_dari')
            ->get();

        if ($bentrok->isEmpty()) {
            return null;
        }

        $p      = $bentrok->first();
        $dari   = $p->berlaku_dari->toDateString();
        $sampai = $p->berlaku_sampai->toDateString();

        return $this->response(
            "Acara bentrok dengan {$p->nama} ({$dari} s/d {$sampai}). Pilih tanggal lain.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'bentrok' => $bentrok->map(fn($x) => [
                    'idPeriode'     => $x->id,
                    'nama'          => $x->nama,
                    'jenis'         => $x->jenis,
                    'berlakuDari'   => $x->berlaku_dari->toDateString(),
                    'berlakuSampai' => $x->berlaku_sampai->toDateString(),
                ])->all(),
            ]
        );
    }

    private function toApiArray(Acara $a): array
    {
        return [
            'idAcara'        => $a->id,
            'judul'          => $a->judul,
            'kategori'       => $a->kategori,
            'tanggalMulai'   => $a->tanggal_mulai->toDateString(),
            'tanggalSelesai' => $a->tanggal_selesai->toDateString(),
            'seharian'       => (bool) $a->seharian,
            'jamMulai'       => $a->jam_mulai ? substr((string) $a->jam_mulai, 0, 5) : null,
            'jamSelesai'     => $a->jam_selesai ? substr((string) $a->jam_selesai, 0, 5) : null,
            'lokasi'         => $a->lokasi,
            'keterangan'     => $a->keterangan,
        ];
    }
}
