<?php

namespace App\Http\Controllers;

use App\Models\RuangKelas;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RuangKelasController extends Controller
{
    use ApiResponser;
    private array $rombels = [1 => 'X', 2 => 'XI', 3 => 'XII'];

    /**
     * Display a listing of the resource for ruang kelas.
     */
    public function index()
    {
        try {
            return $this->response(
                "List Ruang Kelas.", 
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
                'idKelas' => 'required|exists:ruang_kelas,id',
            ], [
                'idKelas.exists' => "Ruang kelas dengan id:{$request->idKelas} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }
            
            $kelas = RuangKelas::find($request->idKelas);
            if ($kelas == null) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->response(
                "Ruang kelas dengan id:{$request->idKelas}.", 
                Response::HTTP_OK, 
                $kelas
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
            $request->merge([
                'jurusan' => strtoupper($request->jurusan),
            ]);
            $validate = Validator::make($request->all(), [
                'noKelas' => 'required|numeric|min:1',
                'tingkat' => 'required|in:1,2,3',
                'jurusan' => 'required|in:MIPA,IPS',
                'limitSiswa' => 'required|numeric|min:1|max:62',
            ], [
                'jurusan.in' => "Jurusan harus MIPA atau IPS.",
                'tingkat.in' => "Tingkatan harus 1, 2, atau 3.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }
            $namaKelas = $this->rombels[(int)$request->tingkat]
                    .' '.$request->jurusan
                    .' '.$request->noKelas;

            $ruangKelas = RuangKelas::create([
                'nama_kelas' => $namaKelas,
                'tingkat'    => $request->tingkat,
                'jurusan'    => $request->jurusan,
                'limit_siswa'    => $request->limitSiswa,
            ]);

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
            $isJurusan = $request->filled('jurusan');
            if ($isJurusan) {
                $request->merge([
                    'jurusan' => strtoupper($request->jurusan),
                ]);
            }

            $validate = Validator::make($request->all(), [
                'idKelas' => 'required',
                'namaKelas' => 'sometimes|string|max:25',
                'noKelas' => 'sometimes|numeric|min:1',
                'tingkat' => 'sometimes|in:1,2,3',
                'jurusan' => 'sometimes|in:MIPA,IPS',
                'limitSiswa' => 'sometimes|numeric|min:1|max:62',
            ], [
                'jurusan.in' => "Jurusan harus MIPA atau IPS.",
                'tingkat.in' => "Tingkatan harus 1, 2, atau 3.",
            ]);

            if($validate->fails()){
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
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            $updateData = [];
            $isTingkat = $request->filled('tingkat');
            $isNoKelas = $request->filled('noKelas');
            $isLimitSiswa = $request->filled('limitSiswa');
            
            if ($isTingkat) {
                $updateData['tingkat'] = $request->tingkat;
            }
            if ($isJurusan) {
                $updateData['jurusan'] = $request->jurusan;
            }
            if ($isLimitSiswa) {
                $updateData['limit_siswa'] = $request->limitSiswa;
            }

            if ($request->filled('namaKelas')) {
                $updateData['nama_kelas'] = $request->namaKelas;
            } else if ($isTingkat || $isJurusan || $isNoKelas) {
                $updateData['nama_kelas'] = $this->rombels[(int)($isTingkat? $request->tingkat : $ruangKelas->tingkat)]
                        .' '.($isJurusan? $request->jurusan : $ruangKelas->jurusan)
                        .' '.($isNoKelas? $request->noKelas : Str::afterLast($ruangKelas->nama_kelas, ' '));
            }
            
            if (empty($updateData)) {
                return $this->response(
                    "Tidak ada data yang diperbarui.",
                    Response::HTTP_BAD_REQUEST
                );
            }
            $ruangKelas->update($updateData);

            return $this->response(
                "Ruang kelas dengan id:{$request->idKelas} berhasil diupdate.", 
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
                'idKelas' => 'required|exists:ruang_kelas,id',
            ], [
                'idKelas.exists' => "Ruang kelas dengan id:{$request->idKelas} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            if (RuangKelas::withTrashed()->find($request->idKelas)->trashed()) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }
            
            RuangKelas::destroy($request->idKelas);

            return $this->response(
                "Ruang kelas dengan id:{$request->idKelas} berhasil dihapus.", 
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
