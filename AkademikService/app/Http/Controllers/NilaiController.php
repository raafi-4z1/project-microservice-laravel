<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Nilai;
use App\Models\SiswaKelas;
use App\Models\PengampuMapel;
use App\Models\PengaturanNilai;
use App\Traits\ApiResponser;
use App\Traits\OtorisasiGuru;

class NilaiController extends Controller
{
    use ApiResponser, OtorisasiGuru;

    // POST /nilai — Admin, SuperAdmin, Guru (X-Guru-Id header required for Guru)
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), $this->aturanNilai([
                'siswa_kelas_id'    => 'required|integer|min:1',
                'pengampu_mapel_id' => 'required|integer|min:1',
            ]));
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $siswaKelas = SiswaKelas::find($request->siswa_kelas_id);
            if (!$siswaKelas) {
                return $this->response("Data siswa kelas dengan id:{$request->siswa_kelas_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $pengampu = PengampuMapel::find($request->pengampu_mapel_id);
            if (!$pengampu) {
                return $this->response("Data pengampu mapel dengan id:{$request->pengampu_mapel_id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            // Pastikan siswa dan pengampu berada di kelas + semester yang sama
            if ($siswaKelas->kelas_id !== $pengampu->kelas_id
                || $siswaKelas->tahun_ajaran !== $pengampu->tahun_ajaran
                || (string) $siswaKelas->semester !== (string) $pengampu->semester
            ) {
                return $this->response(
                    "Siswa dan pengampu mapel tidak berada di kelas/semester yang sama.",
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Validasi ownership untuk Guru: X-Guru-Id harus sesuai dengan guru pengampu
            $guruIdHeader = $request->header('X-Guru-Id');
            if ($guruIdHeader && (int) $guruIdHeader !== (int) $pengampu->guru_id) {
                return $this->response(
                    "Anda hanya dapat menginput nilai untuk mapel yang Anda ampu.",
                    Response::HTTP_FORBIDDEN
                );
            }

            // Cek apakah sudah ada nilai (termasuk soft-deleted) untuk slot yang sama
            $existing = Nilai::withTrashed()
                ->where('siswa_kelas_id', $request->siswa_kelas_id)
                ->where('pengampu_mapel_id', $request->pengampu_mapel_id)
                ->first();

            $slot       = $this->slotUlanganDariRequest($request);
            $rataHarian = $this->rataDariSlot($slot);

            $data = array_merge($slot, [
                'nilai_harian' => $rataHarian,
                'nilai_uts'    => $request->nilai_uts,
                'nilai_uas'    => $request->nilai_uas,
                'nilai_akhir'  => $this->hitungNilaiAkhir(
                    $rataHarian,
                    $request->nilai_uts,
                    $request->nilai_uas,
                    $pengampu->tahun_ajaran,
                    $pengampu->semester
                ),
            ]);

            if ($existing) {
                $existing->restore();
                $existing->update($data);
                $record = $existing->fresh(['siswaKelas', 'pengampuMapel']);
            } else {
                $record = Nilai::create(array_merge([
                    'siswa_kelas_id'    => $request->siswa_kelas_id,
                    'pengampu_mapel_id' => $request->pengampu_mapel_id,
                ], $data));
                $record->load(['siswaKelas', 'pengampuMapel']);
            }

            return $this->response("Nilai berhasil disimpan.", Response::HTTP_CREATED, $this->toApiArray($record));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /nilai/{id} — Admin, SuperAdmin, Guru (ownership check)
    public function update(Request $request, $id)
    {
        try {
            $record = Nilai::find($id);
            if (!$record) {
                return $this->response("Nilai dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            // Validasi ownership untuk Guru
            $guruIdHeader = $request->header('X-Guru-Id');
            if ($guruIdHeader) {
                $pengampu = PengampuMapel::find($record->pengampu_mapel_id);
                if (!$pengampu || (int) $guruIdHeader !== (int) $pengampu->guru_id) {
                    return $this->response(
                        "Anda hanya dapat mengubah nilai untuk mapel yang Anda ampu.",
                        Response::HTTP_FORBIDDEN
                    );
                }
            }

            $validate = Validator::make($request->all(), $this->aturanNilai());
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $updateData = [];
            // PATCH bersifat parsial: hanya slot yang DIKIRIM yang berubah.
            // Kirim null secara eksplisit untuk mengosongkan satu ulangan.
            foreach (range(1, Nilai::MAX_ULANGAN) as $i) {
                if ($request->has("nilai_harian_{$i}")) {
                    $updateData["nilai_harian_{$i}"] = $request->input("nilai_harian_{$i}");
                }
            }
            // Alias lama: nilai_harian tunggal -> slot 1 (lihat aturanNilai()).
            if ($request->has('nilai_harian') && !$request->has('nilai_harian_1')) {
                $updateData['nilai_harian_1'] = $request->input('nilai_harian');
            }
            if ($request->has('nilai_uts')) $updateData['nilai_uts'] = $request->nilai_uts;
            if ($request->has('nilai_uas')) $updateData['nilai_uas'] = $request->nilai_uas;

            if (empty($updateData)) {
                return $this->response('Tidak ada field yang diubah.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $record->update($updateData);

            // Hitung ulang turunan: rata-rata harian dulu, baru nilai_akhir
            $segar      = $record->fresh();
            $rataHarian = $segar->rataUlanganHarian();
            $pengampu   = $pengampu ?? PengampuMapel::find($record->pengampu_mapel_id);
            $record->update([
                'nilai_harian' => $rataHarian,
                'nilai_akhir'  => $this->hitungNilaiAkhir(
                    $rataHarian,
                    $segar->nilai_uts,
                    $segar->nilai_uas,
                    $pengampu->tahun_ajaran,
                    $pengampu->semester
                ),
            ]);

            $record->load(['siswaKelas', 'pengampuMapel']);
            return $this->response("Nilai berhasil diperbarui.", Response::HTTP_OK, $this->toApiArray($record->fresh(['siswaKelas', 'pengampuMapel'])));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /nilai/{id} — Admin, SuperAdmin, Guru (ownership check)
    public function destroy(Request $request, $id)
    {
        try {
            $record = Nilai::find($id);
            if (!$record) {
                return $this->response("Nilai dengan id:{$id} tidak ditemukan.", Response::HTTP_NOT_FOUND);
            }

            $guruIdHeader = $request->header('X-Guru-Id');
            if ($guruIdHeader) {
                $pengampu = PengampuMapel::find($record->pengampu_mapel_id);
                if (!$pengampu || (int) $guruIdHeader !== (int) $pengampu->guru_id) {
                    return $this->response(
                        "Anda hanya dapat menghapus nilai untuk mapel yang Anda ampu.",
                        Response::HTTP_FORBIDDEN
                    );
                }
            }

            $record->delete();
            return $this->response("Nilai berhasil dihapus.", Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/pengampu/{id} — Guru (mapel sendiri), Admin, Karyawan, SuperAdmin
    public function getByPengampu(Request $request, $pengampuId)
    {
        try {
            // Guru hanya boleh membaca nilai pengampu MILIKNYA sendiri.
            if ($tolak = $this->pastikanGuruBolehAksesPengampu($request, (int) $pengampuId)) {
                return $tolak;
            }

            $records = Nilai::with(['siswaKelas', 'pengampuMapel'])
                ->where('pengampu_mapel_id', $pengampuId)
                ->orderBy('siswa_kelas_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Nilai pengampu id:{$pengampuId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/kelas/{kelas_id}?tahun_ajaran=&semester= — Admin, Karyawan, Guru (kelas sendiri), SuperAdmin
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

            if ($tolak = $this->pastikanGuruBolehAksesKelas($request, (int) $kelasId)) {
                return $tolak;
            }

            $records = Nilai::with(['siswaKelas', 'pengampuMapel'])
                ->whereHas('pengampuMapel', function ($q) use ($kelasId, $request) {
                    $q->where('kelas_id', $kelasId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderBy('siswa_kelas_id')
                ->orderBy('pengampu_mapel_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Nilai kelas id:{$kelasId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/siswa/{siswa_id}?tahun_ajaran=&semester= — semua role (Siswa: diri sendiri via X-Siswa-Id check di Gateway)
    public function getBySiswa(Request $request, $siswaId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'nullable|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $records = Nilai::with(['siswaKelas', 'pengampuMapel'])
                ->whereHas('siswaKelas', function ($q) use ($siswaId, $request) {
                    $q->where('siswa_id', $siswaId);
                    if ($request->filled('tahun_ajaran')) $q->where('tahun_ajaran', $request->tahun_ajaran);
                    if ($request->filled('semester'))     $q->where('semester', $request->semester);
                })
                ->orderBy('pengampu_mapel_id')
                ->get()
                ->map(fn($r) => $this->toApiArray($r));

            return $this->response("Nilai siswa id:{$siswaId}.", Response::HTTP_OK, $records);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/raport/siswa/{siswa_id}?tahun_ajaran=&semester= — raport satu siswa per semester
    public function getRaportSiswa(Request $request, $siswaId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
            ], [
                'tahun_ajaran.required' => 'tahun_ajaran wajib diisi untuk cetak raport.',
                'semester.required'     => 'semester wajib diisi untuk cetak raport.',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $nilaiList = Nilai::with(['siswaKelas', 'pengampuMapel'])
                ->whereHas('siswaKelas', fn($q) =>
                    $q->where('siswa_id', $siswaId)
                      ->where('tahun_ajaran', $request->tahun_ajaran)
                      ->where('semester', $request->semester)
                )
                ->orderBy('pengampu_mapel_id')
                ->get();

            $bobot = $this->getBobot($request->tahun_ajaran, $request->semester);

            $mapelNilai = $nilaiList->map(fn($n) => [
                'idNilai'        => $n->id,
                'pengampuMapelId'=> $n->pengampu_mapel_id,
                'guruId'         => $n->pengampuMapel?->guru_id,
                'mapelId'        => $n->pengampuMapel?->mapel_id,
                'nilaiHarian'    => $n->nilai_harian,
                'ulanganHarian'  => $n->ulanganHarian(),
                'nilaiUts'       => $n->nilai_uts,
                'nilaiUas'       => $n->nilai_uas,
                'nilaiAkhir'     => $n->nilai_akhir,
            ]);

            $nilaiAkhirList = $nilaiList->pluck('nilai_akhir')->filter()->values();
            $rataRata = $nilaiAkhirList->count() > 0
                ? round($nilaiAkhirList->avg(), 2)
                : null;

            return $this->response("Raport siswa id:{$siswaId}.", Response::HTTP_OK, [
                'siswaId'      => (int) $siswaId,
                'tahunAjaran'  => $request->tahun_ajaran,
                'semester'     => (int) $request->semester,
                'bobot'        => $bobot,
                'nilai'        => $mapelNilai,
                'rataRata'     => $rataRata,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/raport/kelas/{kelas_id}?tahun_ajaran=&semester= — raport seluruh kelas
    public function getRaportKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            if ($tolak = $this->pastikanGuruBolehAksesKelas($request, (int) $kelasId)) {
                return $tolak;
            }

            // Ambil semua siswa yang aktif di kelas ini pada semester yang diminta
            $siswaKelasRows = SiswaKelas::where('kelas_id', $kelasId)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('semester', $request->semester)
                ->get();

            $bobot = $this->getBobot($request->tahun_ajaran, $request->semester);

            $raport = $siswaKelasRows->map(function ($sk) use ($request, $bobot) {
                $nilaiList = Nilai::with('pengampuMapel')
                    ->where('siswa_kelas_id', $sk->id)
                    ->orderBy('pengampu_mapel_id')
                    ->get();

                $mapelNilai = $nilaiList->map(fn($n) => [
                    'pengampuMapelId' => $n->pengampu_mapel_id,
                    'mapelId'         => $n->pengampuMapel?->mapel_id,
                    'nilaiHarian'     => $n->nilai_harian,
                    'ulanganHarian'   => $n->ulanganHarian(),
                    'nilaiUts'        => $n->nilai_uts,
                    'nilaiUas'        => $n->nilai_uas,
                    'nilaiAkhir'      => $n->nilai_akhir,
                ]);

                $nilaiAkhirList = $nilaiList->pluck('nilai_akhir')->filter();
                $rataRata = $nilaiAkhirList->count() > 0
                    ? round($nilaiAkhirList->avg(), 2)
                    : null;

                return [
                    'siswaId'     => $sk->siswa_id,
                    'siswaKelasId'=> $sk->id,
                    'nilai'       => $mapelNilai,
                    'rataRata'    => $rataRata,
                ];
            });

            return $this->response("Raport kelas id:{$kelasId}.", Response::HTTP_OK, [
                'kelasId'     => (int) $kelasId,
                'tahunAjaran' => $request->tahun_ajaran,
                'semester'    => (int) $request->semester,
                'bobot'       => $bobot,
                'siswa'       => $raport,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /nilai/ranking/kelas/{kelas_id}?tahun_ajaran=&semester=
    // X-Siswa-Id header: jika ada, hanya kembalikan posisi siswa tersebut (bukan full list)
    public function getRankingKelas(Request $request, $kelasId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            if ($tolak = $this->pastikanGuruBolehAksesKelas($request, (int) $kelasId)) {
                return $tolak;
            }

            // Hitung rata-rata nilai_akhir per siswa
            $ranking = DB::table('nilai as n')
                ->join('siswa_kelas as sk', 'n.siswa_kelas_id', '=', 'sk.id')
                ->join('pengampu_mapels as pm', 'n.pengampu_mapel_id', '=', 'pm.id')
                ->whereNull('n.deleted_at')
                ->whereNull('sk.deleted_at')
                ->whereNull('pm.deleted_at')
                ->where('pm.kelas_id', $kelasId)
                ->where('pm.tahun_ajaran', $request->tahun_ajaran)
                ->where('pm.semester', $request->semester)
                ->whereNotNull('n.nilai_akhir')
                ->groupBy('sk.siswa_id', 'sk.id')
                ->select('sk.siswa_id', DB::raw('ROUND(AVG(n.nilai_akhir), 2) as rata_rata'))
                ->orderByDesc('rata_rata')
                ->get();

            $totalSiswa = $ranking->count();

            // Jika Siswa role (X-Siswa-Id ada): hanya kembalikan posisi diri sendiri
            $siswaIdHeader = $request->header('X-Siswa-Id');
            if ($siswaIdHeader) {
                $posisi = $ranking->search(fn($r) => (int) $r->siswa_id === (int) $siswaIdHeader);
                $posisi = $posisi !== false ? $posisi + 1 : null;
                $rataRataSaya = $ranking->firstWhere('siswa_id', $siswaIdHeader)?->rata_rata;

                return $this->response("Peringkat kelas id:{$kelasId}.", Response::HTTP_OK, [
                    'kelasId'     => (int) $kelasId,
                    'tahunAjaran' => $request->tahun_ajaran,
                    'semester'    => (int) $request->semester,
                    'totalSiswa'  => $totalSiswa,
                    'peringkat'   => $posisi,
                    'rataRata'    => $rataRataSaya,
                ]);
            }

            // Admin/Guru/Karyawan: kembalikan seluruh ranking
            $result = $ranking->values()->map(fn($r, $i) => [
                'peringkat' => $i + 1,
                'siswaId'   => $r->siswa_id,
                'rataRata'  => (float) $r->rata_rata,
            ]);

            return $this->response("Peringkat kelas id:{$kelasId}.", Response::HTTP_OK, [
                'kelasId'     => (int) $kelasId,
                'tahunAjaran' => $request->tahun_ajaran,
                'semester'    => (int) $request->semester,
                'totalSiswa'  => $totalSiswa,
                'ranking'     => $result,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /nilai/angkatan/rekap?kelas_ids=1,2,3&tahun_ajaran=&semester=&detail=0|1
     *
     * Data MENTAH per siswa untuk laporan se-angkatan. Sengaja TIDAK memberi
     * peringkat: aturan seri memakai urutan alfabet nama, sedangkan nama ada di
     * SiswaService — jadi peringkat dihitung Gateway setelah nama & status siswa
     * diketahui. Service ini hanya bertanggung jawab atas nilai.
     *
     * Aturan: mapel yang BELUM dinilai dihitung 0. Karena itu penyebut rata-rata
     * adalah jumlah pengampu (mapel) di kelas tsb, bukan jumlah baris nilai.
     */
    public function rekapAngkatan(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'kelas_ids'    => 'required|string',
                'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'required|in:1,2',
                'detail'       => 'sometimes|in:0,1',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $kelasIds = array_values(array_filter(array_map(
                'intval', explode(',', $request->kelas_ids)
            )));
            if (empty($kelasIds)) {
                return $this->response('kelas_ids tidak berisi id yang valid.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $ta     = $request->tahun_ajaran;
            $sem    = $request->semester;
            $detail = (string) $request->input('detail', '0') === '1';

            // Mapel (pengampu) yang berlaku per kelas -> penyebut rata-rata
            $pengampuPerKelas = PengampuMapel::whereIn('kelas_id', $kelasIds)
                ->where('tahun_ajaran', $ta)->where('semester', $sem)
                ->get(['id', 'kelas_id', 'mapel_id'])
                ->groupBy('kelas_id');

            $siswaKelasRows = SiswaKelas::whereIn('kelas_id', $kelasIds)
                ->where('tahun_ajaran', $ta)->where('semester', $sem)
                ->get(['id', 'siswa_id', 'kelas_id']);

            // Semua nilai terkait, diambil sekali lalu dipetakan di memori
            $nilaiMap = Nilai::whereIn('siswa_kelas_id', $siswaKelasRows->pluck('id'))
                ->get(['siswa_kelas_id', 'pengampu_mapel_id', 'nilai_akhir'])
                ->groupBy('siswa_kelas_id');

            $hasil = $siswaKelasRows->map(function ($sk) use ($pengampuPerKelas, $nilaiMap, $detail) {
                $pengampu = $pengampuPerKelas[$sk->kelas_id] ?? collect();
                $nilaiSk  = ($nilaiMap[$sk->id] ?? collect())->keyBy('pengampu_mapel_id');

                $perMapel = $pengampu->map(function ($pm) use ($nilaiSk) {
                    // Belum dinilai / nilai_akhir null -> 0 (aturan sekolah)
                    $na = $nilaiSk[$pm->id]->nilai_akhir ?? null;
                    return [
                        'pengampuMapelId' => $pm->id,
                        'mapelId'         => $pm->mapel_id,
                        'nilaiAkhir'      => $na === null ? 0.0 : (float) $na,
                        'dinilai'         => $na !== null,
                    ];
                })->values();

                $jumlahMapel = $perMapel->count();
                $rataRata = $jumlahMapel > 0
                    ? round($perMapel->avg('nilaiAkhir'), 2)
                    : 0.0;

                $row = [
                    'siswaId'      => (int) $sk->siswa_id,
                    'kelasId'      => (int) $sk->kelas_id,
                    'siswaKelasId' => (int) $sk->id,
                    'jumlahMapel'  => $jumlahMapel,
                    'belumDinilai' => $perMapel->where('dinilai', false)->count(),
                    'rataRata'     => (float) $rataRata,
                ];
                if ($detail) {
                    $row['nilai'] = $perMapel->all();
                }
                return $row;
            })->values();

            return $this->response('Rekap nilai se-angkatan.', Response::HTTP_OK, [
                'tahunAjaran' => $ta,
                'semester'    => (int) $sem,
                'kelasIds'    => $kelasIds,
                'siswa'       => $hasil,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Aturan validasi komponen nilai (dipakai store & update).
     *
     * `nilai_harian` (tunggal) DIPERTAHANKAN sebagai alias slot 1 demi klien lama
     * — kalau dihapus, request lama akan diterima tapi nilainya hilang diam-diam.
     * Klien baru memakai nilai_harian_1..5.
     */
    private function aturanNilai(array $tambahan = []): array
    {
        $aturan = $tambahan;
        foreach (range(1, Nilai::MAX_ULANGAN) as $i) {
            $aturan["nilai_harian_{$i}"] = 'nullable|numeric|min:0|max:100';
        }
        $aturan['nilai_harian'] = 'nullable|numeric|min:0|max:100'; // alias slot 1 (lama)
        $aturan['nilai_uts']    = 'nullable|numeric|min:0|max:100';
        $aturan['nilai_uas']    = 'nullable|numeric|min:0|max:100';
        return $aturan;
    }

    /** Ambil kelima slot ulangan dari request; alias `nilai_harian` -> slot 1. */
    private function slotUlanganDariRequest(Request $request): array
    {
        $slot = [];
        foreach (range(1, Nilai::MAX_ULANGAN) as $i) {
            $slot["nilai_harian_{$i}"] = $request->input("nilai_harian_{$i}");
        }
        if ($slot['nilai_harian_1'] === null && $request->filled('nilai_harian')) {
            $slot['nilai_harian_1'] = $request->input('nilai_harian');
        }
        return $slot;
    }

    /** Rata-rata slot terisi; slot kosong diabaikan (3 terisi -> dibagi 3). */
    private function rataDariSlot(array $slot): ?float
    {
        $terisi = array_values(array_filter($slot, fn($v) => $v !== null));
        return empty($terisi) ? null : round(array_sum($terisi) / count($terisi), 2);
    }

    // Hitung nilai_akhir berdasarkan bobot yang dikonfigurasi; kembalikan null jika ada komponen kosong
    private function hitungNilaiAkhir(?float $harian, ?float $uts, ?float $uas, string $tahunAjaran, $semester): ?float
    {
        if ($harian === null || $uts === null || $uas === null) {
            return null;
        }

        $bobot = $this->getBobot($tahunAjaran, $semester);

        return round(
            ($harian * $bobot['bobotHarian'] + $uts * $bobot['bobotUts'] + $uas * $bobot['bobotUas']) / 100,
            2
        );
    }

    // Ambil bobot dari pengaturan_nilai; gunakan default 40/30/30 jika belum dikonfigurasi
    private function getBobot(string $tahunAjaran, $semester): array
    {
        $config = PengaturanNilai::where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();

        return [
            'bobotHarian' => $config?->bobot_harian ?? 40,
            'bobotUts'    => $config?->bobot_uts    ?? 30,
            'bobotUas'    => $config?->bobot_uas    ?? 30,
        ];
    }

    private function toApiArray($record): array
    {
        $sk = $record->relationLoaded('siswaKelas')   ? $record->siswaKelas   : null;
        $pm = $record->relationLoaded('pengampuMapel') ? $record->pengampuMapel : null;

        return array_filter([
            'idNilai'         => $record->id,
            'siswaKelasId'    => $record->siswa_kelas_id,
            'siswaId'         => $sk?->siswa_id,
            'pengampuMapelId' => $record->pengampu_mapel_id,
            'guruId'          => $pm?->guru_id,
            'mapelId'         => $pm?->mapel_id,
            'kelasId'         => $pm?->kelas_id,
            'tahunAjaran'     => $pm?->tahun_ajaran,
            'semester'        => $pm ? (int) $pm->semester : null,
            // nilaiHarian = RATA-RATA ulangan yang terisi (kolom turunan).
            // ulanganHarian = nilai tiap slot 1..5; null = belum ada ulangan itu.
            'nilaiHarian'     => $record->nilai_harian,
            'ulanganHarian'   => $record->ulanganHarian(),
            'jumlahUlangan'   => count(array_filter($record->ulanganHarian(), fn($v) => $v !== null)),
            'nilaiUts'        => $record->nilai_uts,
            'nilaiUas'        => $record->nilai_uas,
            'nilaiAkhir'      => $record->nilai_akhir,
            'deletedAt'       => $record->deleted_at?->toDateTimeString(),
        ], fn($v) => $v !== null);
    }
}
