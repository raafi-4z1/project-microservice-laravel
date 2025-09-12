<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MapelController extends Controller
{
    use ApiResponser;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'page' => 'sometimes|numeric|min:1',
                'per_page' => 'sometimes|numeric|min:1'
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $columns = [
                'id', 'kode', 'nama', 'keterangan'
            ];

            $perPage = $request->input('per_page', 5);
            $paginator = Mapel::select($columns)
                //->withTrashed()
                ->paginate($perPage)
                ->withQueryString();

            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();

            // Tentukan range halaman
            $start = max(1, $current - 2);
            $end   = min($last,  $current + 2);

            // Buat array URL untuk halaman dalam range
            $urlRange = $paginator->getUrlRange($start, $end);

            // Mapping jadi format yang diinginkan
            $links = collect($urlRange)
                ->map(function($url, $page) use ($current) {
                    return [
                        'query'  => parse_url($url, PHP_URL_QUERY),
                        'label'  => (string) $page,
                        'page'   => (int)    $page,
                        'active' => $page == $current,
                    ];
                })
                ->values()
                ->all();

            // Tambahkan Prev & Next secara manual
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

            // Hasil akhir
            $pageArr = $paginator->toArray();
            $pageArr['links'] = $links;
            
            unset(
                $pageArr['first_page_url'],
                $pageArr['last_page_url'],
                $pageArr['next_page_url'],
                $pageArr['prev_page_url'],
                $pageArr['path']
            );
            
            return $this->response(
                "List Mata Pelajaran.", 
                Response::HTTP_OK, 
                $pageArr
            );
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idPelajaran' => 'required|exists:mapels,id',
            ], [
                'idPelajaran.exists' => "Mata Pelajaran dengan id:{$request->idPelajaran} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapel = Mapel::find($request->idPelajaran);
            if ($mapel == null) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->response(
                "Mata Pelajaran dengan id:{$request->idPelajaran}.", 
                Response::HTTP_OK, 
                $mapel
            );
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'kode' => 'required|string',
                'nama' => 'required|string',
                'keterangan' => 'string',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $mapel = Mapel::create([
                'kode'          => strtoupper($request->kode),
                'nama'          => $request->nama,
                'keterangan'    => $request->keterangan,
            ]);

            return $this->response(
                "Mata Pelajaran berhasil disimpan.", 
                Response::HTTP_CREATED, 
                $mapel
            );
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idPelajaran' => 'required|numeric',
                'kode' => 'sometimes|string',
                'nama' => 'sometimes|string',
                'keterangan' => 'sometimes|string',
            ]);

            if($validate->fails()){
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
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            $updateData = [];
            if ($request->filled('kode')) {
                $updateData['kode'] = strtoupper($request->kode);
            }
            if ($request->filled('nama')) {
                $updateData['nama'] = $request->nama;
            }
            if ($request->filled('keterangan')) {
                $updateData['keterangan'] = $request->keterangan;
            }
            
            if (empty($updateData)) {
                return $this->response(
                    "Tidak ada data yang diperbarui.",
                    Response::HTTP_BAD_REQUEST
                );
            }
            $mapel->update($updateData);

            return $this->response(
                "Mata Pelajaran dengan id:{$request->idPelajaran} berhasil diupdate.", 
                Response::HTTP_ACCEPTED, 
                $mapel
            );
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idPelajaran' => 'required|numeric|exists:mapels,id',
            ], [
                'idPelajaran.exists' => "Mata Pelajaran dengan id:{$request->idPelajaran} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            if (Mapel::withTrashed()->find($request->idPelajaran)->trashed()) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }
            
            Mapel::destroy($request->idPelajaran);

            return $this->response(
                "Mata Pelajaran dengan id:{$request->idPelajaran} berhasil dihapus.", 
                Response::HTTP_ACCEPTED
            );
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }
}
