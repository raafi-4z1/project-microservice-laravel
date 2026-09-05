<?php

namespace App\Http\Controllers;

use App\Models\RuangKelas;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RuangKelasController extends Controller
{
    use ApiResponser;
    private array $rombels = [1 => 'X', 2 => 'XI', 3 => 'XII'];

    public function index(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'page'     => 'sometimes|numeric|min:1',
                // Batas atas 200 seragam di seluruh endpoint berpaginasi. Tanpa batas,
                // satu request `per_page=100000` memaksa query tak terbatas —
                // tidak terasa selagi data kecil, mahal begitu data bertambah.
                'per_page' => 'sometimes|numeric|min:1|max:200',
                'search'   => 'sometimes|string|max:100',
                'tingkat'  => 'sometimes|in:1,2,3',
                'jurusan'  => 'sometimes|string|max:20',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $columns = ['id', 'nama_kelas', 'tingkat', 'jurusan', 'limit_siswa'];
            $perPage  = $request->input('per_page', 5);

            $query = RuangKelas::select($columns);

            // Filter tepat untuk laporan se-angkatan (Gateway: ranking/angkatan)
            // — supaya tidak perlu menarik seluruh kelas lalu menyaring di memori.
            if ($request->filled('tingkat')) {
                $query->where('tingkat', $request->tingkat);
            }
            if ($request->filled('jurusan')) {
                $query->where('jurusan', strtoupper($request->jurusan));
            }

            // Cari di nama kelas atau jurusan
            if ($request->filled('search')) {
                $s = $request->input('search');
                $query->where(function ($q) use ($s) {
                    $q->where('nama_kelas', 'like', "%{$s}%")
                      ->orWhere('jurusan', 'like', "%{$s}%");
                });
            }

            $paginator = $query
                ->paginate($perPage)
                ->withQueryString();

            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();
            $start   = max(1, $current - 2);
            $end     = min($last, $current + 2);

            $urlRange = $paginator->getUrlRange($start, $end);

            $links = collect($urlRange)
                ->map(function ($url, $page) use ($current) {
                    return [
                        'query'  => parse_url($url, PHP_URL_QUERY),
                        'label'  => (string) $page,
                        'page'   => (int) $page,
                        'active' => $page == $current,
                    ];
                })
                ->values()
                ->all();

            if ($paginator->onFirstPage() === false) {
                array_unshift($links, [
                    'query'  => parse_url($paginator->previousPageUrl(), PHP_URL_QUERY),
                    'label'  => '&laquo; Previous',
                    'page'   => $current - 1,
                    'active' => false,
                ]);
            }

            if ($paginator->hasMorePages()) {
                $links[] = [
                    'query'  => parse_url($paginator->nextPageUrl(), PHP_URL_QUERY),
                    'label'  => 'Next &raquo;',
                    'page'   => $current + 1,
                    'active' => false,
                ];
            }

            $pageArr          = $paginator->toArray();
            $pageArr['data']  = collect($pageArr['data'])->map(fn($item) => $this->toApiArray($item))->all();
            $pageArr['links'] = $links;

            unset(
                $pageArr['first_page_url'],
                $pageArr['last_page_url'],
                $pageArr['next_page_url'],
                $pageArr['prev_page_url'],
                $pageArr['path']
            );

            return $this->response("List Ruang Kelas.", Response::HTTP_OK, $pageArr);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idKelas' => 'required|exists:ruang_kelas,id',
            ], [
                'idKelas.exists' => "Ruang kelas dengan id:{$request->idKelas} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $kelas = RuangKelas::find($request->idKelas);
            if ($kelas === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            return $this->response(
                "Ruang kelas dengan id:{$request->idKelas}.",
                Response::HTTP_OK,
                $this->toApiArray($kelas->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->merge(['jurusan' => strtoupper($request->jurusan)]);

            $validate = Validator::make($request->all(), [
                'noKelas'    => 'required|numeric|min:1',
                'tingkat'    => 'required|in:1,2,3',
                'jurusan'    => 'required|in:MIPA,IPS',
                'limitSiswa' => 'required|numeric|min:1|max:62',
            ], [
                'jurusan.in' => "Jurusan harus MIPA atau IPS.",
                'tingkat.in' => "Tingkatan harus 1, 2, atau 3.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $namaKelas = $this->rombels[(int) $request->tingkat]
                . ' ' . $request->jurusan
                . ' ' . $request->noKelas;

            $ruangKelas = RuangKelas::create([
                'nama_kelas'  => $namaKelas,
                'tingkat'     => $request->tingkat,
                'jurusan'     => $request->jurusan,
                'limit_siswa' => $request->limitSiswa,
            ]);

            return $this->response(
                "Ruang kelas berhasil disimpan.",
                Response::HTTP_CREATED,
                $this->toApiArray($ruangKelas->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $isJurusan = $request->filled('jurusan');
            if ($isJurusan) {
                $request->merge(['jurusan' => strtoupper($request->jurusan)]);
            }

            $validate = Validator::make($request->all(), [
                'idKelas'    => 'required',
                'namaKelas'  => 'sometimes|string|max:25',
                'noKelas'    => 'sometimes|numeric|min:1',
                'tingkat'    => 'sometimes|in:1,2,3',
                'jurusan'    => 'sometimes|in:MIPA,IPS',
                'limitSiswa' => 'sometimes|numeric|min:1|max:62',
            ], [
                'jurusan.in' => "Jurusan harus MIPA atau IPS.",
                'tingkat.in' => "Tingkatan harus 1, 2, atau 3.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $ruangKelas = RuangKelas::withTrashed()->find($request->idKelas);
            if (!$ruangKelas) {
                return $this->response(
                    "Ruang kelas dengan id:{$request->idKelas} tidak ditemukan.",
                    Response::HTTP_NOT_FOUND
                );
            }
            if ($ruangKelas->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $updateData = [];
            $isTingkat  = $request->filled('tingkat');
            $isNoKelas  = $request->filled('noKelas');

            if ($isTingkat) {
                $updateData['tingkat'] = $request->tingkat;
            }
            if ($isJurusan) {
                $updateData['jurusan'] = $request->jurusan;
            }
            if ($request->filled('limitSiswa')) {
                $updateData['limit_siswa'] = $request->limitSiswa;
            }

            if ($request->filled('namaKelas')) {
                $updateData['nama_kelas'] = $request->namaKelas;
            } elseif ($isTingkat || $isJurusan || $isNoKelas) {
                $updateData['nama_kelas'] = $this->rombels[(int) ($isTingkat ? $request->tingkat : $ruangKelas->tingkat)]
                    . ' ' . ($isJurusan ? $request->jurusan : $ruangKelas->jurusan)
                    . ' ' . ($isNoKelas ? $request->noKelas : Str::afterLast($ruangKelas->nama_kelas, ' '));
            }

            if (empty($updateData)) {
                return $this->response("Tidak ada data yang diperbarui.", Response::HTTP_BAD_REQUEST);
            }

            $ruangKelas->update($updateData);

            return $this->response(
                "Ruang kelas dengan id:{$request->idKelas} berhasil diupdate.",
                Response::HTTP_ACCEPTED,
                $this->toApiArray($ruangKelas->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validate = Validator::make(['id' => $id], [
                'id' => 'required|exists:ruang_kelas,id',
            ], [
                'id.exists' => "Ruang kelas dengan id:{$id} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $kelasCheck = RuangKelas::withTrashed()->find($id);
            if (!$kelasCheck || $kelasCheck->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            RuangKelas::destroy($id);

            return $this->response(
                "Ruang kelas dengan id:{$id} berhasil dihapus.",
                Response::HTTP_ACCEPTED
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        $map = [
            'id'          => 'idKelas',
            'nama_kelas'  => 'namaKelas',
            'limit_siswa' => 'limitSiswa',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }
        return $result;
    }

    /**
     * GET /{prefix}/nama — daftar id + nama saja, TERMASUK yang sudah dihapus.
     *
     * Dua kebutuhan yang dijawab satu endpoint:
     *
     *  1. Resolusi nama historis. Endpoint riwayat hanya membalas id, dan klien
     *     meresolusinya dari cache roster AKTIF. Entitas yang sudah lulus/pindah/
     *     dihapus tak ada di sana, sehingga tampil sebagai "#<id>". Karena itu
     *     endpoint ini memakai withTrashed() — justru yang non-aktif yang jadi
     *     masalah, dan menyembunyikannya di sini akan mempertahankan bug-nya.
     *
     *  2. Cache nama sekolah besar. `/all` dibatasi per_page<=200; sekolah dengan
     *     ribuan siswa akan terpotong dan sisanya tampil "#<id>". Endpoint ini
     *     ringan (2 kolom, tanpa foto/PII) sehingga aman dimuat sekaligus.
     *
     * `?ids=1,2,3` menyaring ke id tertentu (dipakai Gateway saat memperkaya
     * respons); tanpa `ids` mengembalikan seluruhnya.
     *
     * BUKAN pengganti `/all` untuk dropdown: daftar ini memuat entitas non-aktif.
     */
    public function nama(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'ids' => 'sometimes|string|max:4000',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $q = RuangKelas::withTrashed()->select('id', 'nama_kelas');

            if ($request->filled('ids')) {
                $ids = collect(explode(',', (string) $request->input('ids')))
                    ->map(fn($v) => (int) trim($v))
                    ->filter(fn($v) => $v > 0)
                    ->unique()
                    ->values();

                // ids= yang dikirim tapi tak menyisakan angka valid harus balas
                // kosong, bukan SELURUH tabel — kalau tidak, satu salah ketik di
                // klien menarik seluruh data sekolah.
                if ($ids->isEmpty()) {
                    return $this->response('Daftar nama.', Response::HTTP_OK, []);
                }
                $q->whereIn('id', $ids->all());
            }

            $rows = $q->orderBy('nama_kelas')->get()->map(fn($r) => [
                'idKelas'   => $r->id,
                'namaKelas' => $r->nama_kelas,
            ])->all();

            return $this->response('Daftar nama.', Response::HTTP_OK, $rows);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
