<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Guru;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class GuruController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
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
                'id', 'foto', 'nama_lengkap', 'nip', 'email', 'jabatan', 'status_kepegawaian'
            ];
            
            $perPage = $request->input('per_page', 5);
            $paginator = Guru::select($columns)
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
                "List Data Guru.",
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
                'idGuru' => 'required|exists:gurus,id',
            ], [
                'idGuru.exists' => "Guru dengan id:{$request->idGuru} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::find($request->idGuru);
            if ($guru == null) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->response(
                "Guru dengan id:{$request->idGuru}.", 
                Response::HTTP_OK, 
                $guru
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
    public function store(Request $request) {
        $path = '';
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
                'nik' => 'required|numeric',
                'nip' => 'required|numeric',
                'namaLengkap' => 'required',
                'telephone' => 'required|numeric',
                'jenisKelamin' => 'required|in:Laki-Laki,Perempuan',
                'tempatLahir' => 'required',
                'tanggalLahir' => 'required|date',
                'agama' => 'sometimes',
                'statusPernikahan' => 'sometimes',
                'alamat' => 'required',
                'foto' => [
                    'required',
                    'file',
                    'image',
                    'mimes:jpeg,png,jpg,gif',
                    'max:512', // jadi 0.5 MB
                    'bail',
                    'dimensions:min_width=300,min_height=300,max_width=600,max_height=600,ratio=1/1',
                ],
                'statusKepegawaian' => 'required',
                'nomorSKPengangkatan' => 'sometimes|numeric',
                'tanggalMasuk' => 'required|date',
                'jabatan' => 'required',
                'nomorSertifikasi' => 'sometimes|numeric',
                'pendidikanTerakhir' => 'required',
                'jurusan' => 'required',
                'universitas' => 'required',
                'tahunLulus' => 'required',
                'pelatihan' => 'sometimes',
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $file = $request->file('foto');
            $directoryStorage = 'profiles';
            // simpan di storage/app/private/profiles
            $path = $file->storeAs(
                $directoryStorage,
                $this->generateUniqueFilename(
                    $directoryStorage,
                    $file->getClientOriginalExtension()
                ),
                'private'
            );

            $data = [
                'email' => $request->email,
                'nik' => $request->nik,
                'nip' => $request->nip,
                'nama_lengkap' => $request->namaLengkap,
                'telephone' => $request->telephone,
                'jenis_kelamin' => $request->jenisKelamin,
                'tempat_lahir' => $request->tempatLahir,
                'tanggal_lahir' => $request->tanggalLahir,
                'alamat' => $request->alamat,
                'foto' => $path,
                'status_kepegawaian' => $request->statusKepegawaian,
                'tanggal_masuk' => $request->tanggalMasuk,
                'jabatan' => $request->jabatan,
                'pendidikan_terakhir' => $request->pendidikanTerakhir,
                'jurusan' => $request->jurusan,
                'universitas' => $request->universitas,
                'tahun_lulus' => $request->tahunLulus,
            ];
            
            if ($request->filled('agama')) {
                $data['agama'] = $request->agama;
            }
            if ($request->filled('statusPernikahan')) {
                $data['status_pernikahan'] = $request->statusPernikahan;
            }
            if ($request->filled('nomorSKPengangkatan')) {
                $data['nomor_sk_pengangkatan'] = $request->nomorSKPengangkatan;
            }
            if ($request->filled('nomorSertifikasi')) {
                $data['nomor_sertifikasi'] = $request->nomorSertifikasi;
            }
            if ($request->filled('pelatihan')) {
                $data['pelatihan'] = $request->pelatihan;
            }

            Guru::create($data);
            
            return $this->response(
                "Data Guru berhasil disimpan.", 
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            if (!empty($path) && Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }

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
                'idGuru' => 'required|exists:gurus,id',
                'nik' => 'sometimes|numeric',
                'nip' => 'sometimes|numeric',
                'namaLengkap' => 'sometimes',
                'telephone' => 'sometimes|numeric',
                'jenisKelamin' => 'sometimes|in:Laki-Laki,Perempuan',
                'tempatLahir' => 'sometimes',
                'tanggalLahir' => 'sometimes|date',
                'agama' => 'sometimes',
                'statusPernikahan' => 'sometimes',
                'alamat' => 'sometimes',
                'foto' => [
                    'sometimes',
                    'file',
                    'image',
                    'mimes:jpeg,png,jpg,gif',
                    'max:512', // jadi 0.5 MB
                    'bail',
                    'dimensions:min_width=300,min_height=300,max_width=600,max_height=600,ratio=1/1',
                ],
                'statusKepegawaian' => 'sometimes',
                'nomorSKPengangkatan' => 'sometimes|numeric',
                'tanggalMasuk' => 'sometimes|date',
                'jabatan' => 'sometimes',
                'nomorSertifikasi' => 'sometimes|numeric',
                'pendidikanTerakhir' => 'sometimes',
                'jurusan' => 'sometimes',
                'universitas' => 'sometimes',
                'tahunLulus' => 'sometimes',
                'pelatihan' => 'sometimes',
            ], [
                'idGuru.exists' => "Guru dengan id:{$request->idGuru} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::find($request->idGuru);
            if ($guru == null) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }

            $updateData = [];
            if ($request->filled('nik')) {
                $updateData['nik'] = $request->nik;
            }
            if ($request->filled('nip')) {
                $updateData['nip'] = $request->nip;
            }
            if ($request->filled('namaLengkap')) {
                $updateData['nama_lengkap'] = $request->namaLengkap;
            }
            if ($request->filled('telephone')) {
                $updateData['telephone'] = $request->telephone;
            }
            if ($request->filled('jenisKelamin')) {
                $updateData['jenis_kelamin'] = $request->jenisKelamin;
            }
            if ($request->filled('tempatLahir')) {
                $updateData['tempat_lahir'] = $request->tempatLahir;
            }
            if ($request->filled('tanggalLahir')) {
                $updateData['tanggal_lahir'] = $request->tanggalLahir;
            }
            if ($request->filled('alamat')) {
                $updateData['alamat'] = $request->alamat;
            }
            if ($request->filled('foto')) {
                $directoryStorage = 'profiles';
                $file = $request->file('foto');
                // simpan foto baru
                $path = $file->storeAs(
                    $directoryStorage,
                    $this->generateUniqueFilename(
                        $directoryStorage,
                        $file->getClientOriginalExtension()
                    ),
                    'private'
                );

                // Hapus foto lama
                if ($guru->foto && Storage::exists($guru->foto)) {
                    Storage::delete($guru->foto);
                }

                $updateData['foto']  = $path;
            }
            if ($request->filled('statusKepegawaian')) {
                $updateData['status_kepegawaian'] = $request->statusKepegawaian;
            }
            if ($request->filled('tanggalMasuk')) {
                $updateData['tanggal_masuk'] = $request->tanggalMasuk;
            }
            if ($request->filled('jabatan')) {
                $updateData['jabatan'] = $request->jabatan;
            }
            if ($request->filled('pendidikanTerakhir')) {
                $updateData['pendidikan_terakhir'] = $request->pendidikanTerakhir;
            }
            if ($request->filled('jurusan')) {
                $updateData['jurusan'] = $request->jurusan;
            }
            if ($request->filled('universitas')) {
                $updateData['universitas'] = $request->universitas;
            }
            if ($request->filled('tahunLulus')) {
                $updateData['tahun_lulus'] = $request->tahunLulus;
            }
            if ($request->filled('agama')) {
                $updateData['agama'] = $request->agama;
            }
            if ($request->filled('statusPernikahan')) {
                $updateData['status_pernikahan'] = $request->statusPernikahan;
            }
            if ($request->filled('nomorSKPengangkatan')) {
                $updateData['nomor_sk_pengangkatan'] = $request->nomorSKPengangkatan;
            }
            if ($request->filled('nomorSertifikasi')) {
                $updateData['nomor_sertifikasi'] = $request->nomorSertifikasi;
            }
            if ($request->filled('pelatihan')) {
                $updateData['pelatihan'] = $request->pelatihan;
            }

            if (empty($updateData)) {
                return $this->response(
                    "Tidak ada data yang diperbarui.",
                    Response::HTTP_BAD_REQUEST
                );
            }
            $guru->update($updateData);

            return $this->response(
                "Guru dengan id:{$request->idGuru} berhasil diupdate.", 
                Response::HTTP_ACCEPTED,
                ['email' => $guru->email]
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
                'idGuru' => 'required|exists:gurus,id',
            ], [
                'idGuru.exists' => "Guru dengan id:{$request->idGuru} tidak ada di database.",
            ]);

            if($validate->fails()){
                return $this->response(
                    $validate->errors()->first(), 
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::withTrashed()->find($request->idGuru);
            if ($guru->trashed()) {
                return $this->response(
                    "Data sudah dihapus.", 
                    Response::HTTP_NOT_FOUND
                );
            }
            
            $guru->delete();

            return $this->response(
                "Guru dengan id:{$request->idGuru} berhasil dihapus.", 
                Response::HTTP_ACCEPTED,
                ['email' => $guru->email]
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
     * Generate nama file unik
     * format yyyy-MM-dd_randomString.ext
     */
    private function generateUniqueFilename(string $directory, string $extension): string
    {
        do {
            $filename = Carbon::now()->format('Y-m-d')
                      . '_' . Str::random(12)
                      . '.' . $extension;
        } while (Storage::disk()->exists("{$directory}/{$filename}"));

        return $filename;
    }
}
