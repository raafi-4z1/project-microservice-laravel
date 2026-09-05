<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MapelController extends Controller
{
    use ApiResponser;

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
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $columns = ['id', 'kode', 'nama_pelajaran', 'keterangan'];
            $perPage = $request->input('per_page', 5);

            $query = Mapel::select($columns);

            // Cari di kode atau nama pelajaran
            if ($request->filled('search')) {
                $s = $request->input('search');
                $query->where(function ($q) use ($s) {
                    $q->where('kode', 'like', "%{$s}%")
                      ->orWhere('nama_pelajaran', 'like', "%{$s}%");
                });
            }

            $paginator = $query->paginate($perPage)->withQueryString();

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

            $pageArr         = $paginator->toArray();
            $pageArr['data'] = collect($pageArr['data'])->map(fn($item) => $this->toApiArray($item))->all();
            $pageArr['links'] = $links;

            unset(
                $pageArr['first_page_url'],
                $pageArr['last_page_url'],
                $pageArr['next_page_url'],
                $pageArr['prev_page_url'],
                $pageArr['path']
            );

            return $this->response("List Mata Pelajaran.", Response::HTTP_OK, $pageArr);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idPelajaran' => 'required|exists:mapels,id',
            ], [
                'idPelajaran.exists' => "Mata Pelajaran dengan id:{$request->idPelajaran} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapel = Mapel::find($request->idPelajaran);
            if ($mapel === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            return $this->response(
                "Mata Pelajaran dengan id:{$request->idPelajaran}.",
                Response::HTTP_OK,
                $this->toApiArray($mapel->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'kode'          => 'required|string',
                'namaPelajaran' => 'required|string',
                'keterangan'    => 'sometimes|string',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapel = Mapel::create([
                'kode'          => strtoupper($request->kode),
                'nama_pelajaran' => $request->namaPelajaran,
                'keterangan'    => $request->keterangan,
            ]);

            return $this->response(
                "Mata Pelajaran berhasil disimpan.",
                Response::HTTP_CREATED,
                $this->toApiArray($mapel->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idPelajaran'   => 'required|numeric',
                'kode'          => 'sometimes|string',
                'namaPelajaran' => 'sometimes|string',
                'keterangan'    => 'sometimes|string',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapel = Mapel::withTrashed()->find($request->idPelajaran);
            if (!$mapel) {
                return $this->response(
                    "Mata Pelajaran dengan id:{$request->idPelajaran} tidak ditemukan.",
                    Response::HTTP_NOT_FOUND
                );
            }
            if ($mapel->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $updateData = [];
            if ($request->filled('kode')) {
                $updateData['kode'] = strtoupper($request->kode);
            }
            if ($request->filled('namaPelajaran')) {
                $updateData['nama_pelajaran'] = $request->namaPelajaran;
            }
            if ($request->filled('keterangan')) {
                $updateData['keterangan'] = $request->keterangan;
            }

            if (empty($updateData)) {
                return $this->response("Tidak ada data yang diperbarui.", Response::HTTP_BAD_REQUEST);
            }

            $mapel->update($updateData);

            return $this->response(
                "Mata Pelajaran dengan id:{$request->idPelajaran} berhasil diupdate.",
                Response::HTTP_ACCEPTED,
                $this->toApiArray($mapel->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validate = Validator::make(['id' => $id], [
                'id' => 'required|numeric|exists:mapels,id',
            ], [
                'id.exists' => "Mata Pelajaran dengan id:{$id} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapelCheck = Mapel::withTrashed()->find($id);
            if (!$mapelCheck || $mapelCheck->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            Mapel::destroy($id);

            return $this->response(
                "Mata Pelajaran dengan id:{$id} berhasil dihapus.",
                Response::HTTP_ACCEPTED
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        $map = [
            'id'             => 'idPelajaran',
            'nama_pelajaran' => 'namaPelajaran',
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

            $q = Mapel::withTrashed()->select('id', 'nama_pelajaran');

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

            $rows = $q->orderBy('nama_pelajaran')->get()->map(fn($r) => [
                'idPelajaran'   => $r->id,
                'namaPelajaran' => $r->nama_pelajaran,
            ])->all();

            return $this->response('Daftar nama.', Response::HTTP_OK, $rows);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
