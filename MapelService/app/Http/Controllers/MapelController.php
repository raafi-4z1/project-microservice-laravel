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
    public function index()
    {
        try {
            return $this->response(
                "List Mata Pelajaran.", 
                Response::HTTP_OK, 
                Mapel::all()->toArray()
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
                'idPelajaran' => 'required',
                'nama' => 'string',
                'keterangan' => 'string',
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
