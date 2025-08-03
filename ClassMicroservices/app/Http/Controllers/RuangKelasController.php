<?php

namespace App\Http\Controllers;

use App\Models\RuangKelas;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RuangKelasController extends Controller
{
    use ApiResponser;
    /**
     * Display a listing of the resource for ruang kelas.
     */
    public function index()
    {
        try {
            return $this->response(
                "Semua Ruang kelas.", 
                Response::HTTP_OK, 
                RuangKelas::all()->toArray()
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
                'idRuangKelas' => 'required|exists:ruang_kelas,id',
            ], [
                'idRuangKelas.exists' => 'Ruang kelas dengan ID tersebut tidak ada di database.',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_BAD_REQUEST,
                    $validate->errors()
                );
            }

            return $this->response(
                "Ruang kelas dengan id:{$request->idRuangKelas}.", 
                Response::HTTP_OK, 
                RuangKelas::find($request->idRuangKelas)
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
                'namaRuangKelas' => 'required|string|max:25',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_BAD_REQUEST,
                    $validate->errors()
                );
            }

            $ruangKelas = new RuangKelas();
            $ruangKelas->name = $request->namaRuangKelas;
            $ruangKelas->save();

            return $this->response(
                "Ruang kelas berhasil disimpan.", 
                Response::HTTP_CREATED, 
                $ruangKelas
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
                'idRuangKelas' => 'required|exists:ruang_kelas,id',
                'namaRuangKelas' => 'required|string|max:25',
            ], [
                'idRuangKelas.exists' => 'Ruang kelas dengan ID tersebut tidak ada di database.',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_BAD_REQUEST,
                    $validate->errors()
                );
            }

            if (RuangKelas::withTrashed()->find($request->idRuangKelas)->trashed()) {
                return $this->response(
                    "Data sudah dihapus sebelumnya.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            $ruangKelas = RuangKelas::find($request->idRuangKelas);
            $ruangKelas->name = $request->namaRuangKelas;
            $ruangKelas->save();

            return $this->response(
                "Ruang kelas dengan id:{$request->idRuangKelas} berhasil diupdate.", 
                Response::HTTP_ACCEPTED, 
                $ruangKelas
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
                'idRuangKelas' => 'required|exists:ruang_kelas,id',
            ], [
                'idRuangKelas.exists' => 'Ruang kelas dengan ID tersebut tidak ada di database.',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_BAD_REQUEST,
                    $validate->errors()
                );
            }

            if (RuangKelas::withTrashed()->find($request->idRuangKelas)->trashed()) {
                return $this->response(
                    "Data sudah dihapus sebelumnya.", 
                    Response::HTTP_NOT_FOUND
                );
            }
            
            RuangKelas::destroy($request->idRuangKelas);

            return $this->response(
                "Ruang kelas dengan id:{$request->idRuangKelas} berhasil dihapus.", 
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
