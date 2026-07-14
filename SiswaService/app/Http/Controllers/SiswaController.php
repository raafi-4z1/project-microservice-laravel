<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Siswa;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;

class SiswaController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'page'     => 'sometimes|numeric|min:1',
                'per_page' => 'sometimes|numeric|min:1',
                'search'   => 'sometimes|string|max:100',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $columns = [
                'id', 'nama_lengkap', 'nisn', 'jenis_kelamin',
                'tempat_lahir', 'tanggal_lahir', 'tanggal_masuk', 'status',
            ];

            $perPage  = $request->input('per_page', 5);

            $query = Siswa::select($columns);

            // Cari di nama lengkap atau NISN
            if ($request->filled('search')) {
                $s = $request->input('search');
                $query->where(function ($q) use ($s) {
                    $q->where('nama_lengkap', 'like', "%{$s}%")
                      ->orWhere('nisn', 'like', "%{$s}%");
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

            return $this->response("List Data Siswa.", Response::HTTP_OK, $pageArr);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idSiswa' => 'required|exists:siswas,id',
            ], [
                'idSiswa.exists' => "Siswa dengan id:{$request->idSiswa} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $siswa = Siswa::find($request->idSiswa);
            if ($siswa === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            return $this->response(
                "Siswa dengan id:{$request->idSiswa}.",
                Response::HTTP_OK,
                $this->toApiArray($siswa->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        $path = '';
        try {
            $validate = Validator::make($request->all(), [
                'email'        => 'required|email',
                'nisn'         => 'required|numeric',
                'namaLengkap'  => 'required',
                'telephone'    => 'required|numeric',
                'jenisKelamin' => 'required|in:Laki-Laki,Perempuan',
                'tempatLahir'  => 'required',
                'tanggalLahir' => 'required|date',
                'agama'        => 'sometimes',
                'tanggalMasuk' => 'required|date',
                'alamat'       => 'required',
                'foto'         => [
                    'required', 'file', 'image',
                    'mimes:jpeg,png,jpg', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
                'namaAyah'     => 'sometimes',
                'namaIbu'      => 'required',
                'pekerjaanAyah'=> 'sometimes',
                'pekerjaanIbu' => 'sometimes',
                'noTelpAyah'   => 'sometimes|numeric',
                'noTelpIbu'    => 'sometimes|numeric',
                'namaWali'     => 'sometimes',
                'hubunganWali' => 'sometimes',
                'noTelpWali'   => 'sometimes|numeric',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $directoryStorage = 'profiles';
            $filename = $this->generateUniqueFilename($directoryStorage, 'webp');
            $path     = "{$directoryStorage}/{$filename}";
            Storage::disk('private')->put($path, (string) $this->convertImage($request->file('foto')));

            $data = [
                'email'        => $request->email,
                'nisn'         => $request->nisn,
                'nama_lengkap' => $request->namaLengkap,
                'telephone'    => $request->telephone,
                'jenis_kelamin'=> $request->jenisKelamin,
                'tempat_lahir' => $request->tempatLahir,
                'tanggal_lahir'=> $request->tanggalLahir,
                'alamat'       => $request->alamat,
                'foto'         => $path,
                'tanggal_masuk'=> $request->tanggalMasuk,
                'nama_ibu'     => $request->namaIbu,
            ];

            if ($request->filled('agama')) {
                $data['agama'] = $request->agama;
            }
            if ($request->filled('namaAyah')) {
                $data['nama_ayah'] = $request->namaAyah;
            }
            if ($request->filled('pekerjaanAyah')) {
                $data['pekerjaan_ayah'] = $request->pekerjaanAyah;
            }
            if ($request->filled('pekerjaanIbu')) {
                $data['pekerjaan_ibu'] = $request->pekerjaanIbu;
            }
            if ($request->filled('noTelpAyah')) {
                $data['no_telp_ayah'] = $request->noTelpAyah;
            }
            if ($request->filled('noTelpIbu')) {
                $data['no_telp_ibu'] = $request->noTelpIbu;
            }
            if ($request->filled('namaWali')) {
                $data['nama_wali'] = $request->namaWali;
            }
            if ($request->filled('hubunganWali')) {
                $data['hubungan_wali'] = $request->hubunganWali;
            }
            if ($request->filled('noTelpWali')) {
                $data['no_telp_wali'] = $request->noTelpWali;
            }

            $siswa = Siswa::create($data);

            return $this->response(
                "Data Siswa berhasil disimpan.",
                Response::HTTP_CREATED,
                ['idSiswa' => $siswa->id]
            );
        } catch (Exception $e) {
            if (!empty($path) && Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idSiswa'      => 'required|exists:siswas,id',
                'nisn'         => 'sometimes|numeric',
                'namaLengkap'  => 'sometimes',
                'telephone'    => 'sometimes|numeric',
                'jenisKelamin' => 'sometimes|in:Laki-Laki,Perempuan',
                'status'       => 'sometimes|in:Aktif,Lulus,Berhenti,Pindah',
                'statusDate'   => 'sometimes|date',
                'tempatLahir'  => 'sometimes',
                'tanggalLahir' => 'sometimes|date',
                'agama'        => 'sometimes',
                'tanggalMasuk' => 'sometimes|date',
                'alamat'       => 'sometimes',
                'foto'         => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
                'namaAyah'     => 'sometimes',
                'namaIbu'      => 'sometimes',
                'pekerjaanAyah'=> 'sometimes',
                'pekerjaanIbu' => 'sometimes',
                'noTelpAyah'   => 'sometimes|numeric',
                'noTelpIbu'    => 'sometimes|numeric',
                'namaWali'     => 'sometimes',
                'hubunganWali' => 'sometimes',
                'noTelpWali'   => 'sometimes|numeric',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $siswa = Siswa::find($request->idSiswa);
            if ($siswa === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $updateData = [];
            if ($request->filled('nisn')) {
                $updateData['nisn'] = $request->nisn;
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
            if ($request->filled('status')) {
                $updateData['status'] = $request->status;
            }
            if ($request->filled('statusDate')) {
                $updateData['status_date'] = $request->statusDate;
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
            if ($request->filled('tanggalMasuk')) {
                $updateData['tanggal_masuk'] = $request->tanggalMasuk;
            }
            if ($request->hasFile('foto')) {
                $directoryStorage = 'profiles';
                $filename = $this->generateUniqueFilename($directoryStorage, 'webp');
                $newPath  = "{$directoryStorage}/{$filename}";
                Storage::disk('private')->put($newPath, (string) $this->convertImage($request->file('foto')));

                // getRawOriginal: ambil PATH asli, bukan accessor foto (base64)
                $oldFoto = $siswa->getRawOriginal('foto');
                if ($oldFoto && Storage::disk('private')->exists($oldFoto)) {
                    Storage::disk('private')->delete($oldFoto);
                }
                $updateData['foto'] = $newPath;
            }
            if ($request->filled('agama')) {
                $updateData['agama'] = $request->agama;
            }
            if ($request->filled('namaAyah')) {
                $updateData['nama_ayah'] = $request->namaAyah;
            }
            if ($request->filled('namaIbu')) {
                $updateData['nama_ibu'] = $request->namaIbu;
            }
            if ($request->filled('pekerjaanAyah')) {
                $updateData['pekerjaan_ayah'] = $request->pekerjaanAyah;
            }
            if ($request->filled('pekerjaanIbu')) {
                $updateData['pekerjaan_ibu'] = $request->pekerjaanIbu;
            }
            if ($request->filled('noTelpAyah')) {
                $updateData['no_telp_ayah'] = $request->noTelpAyah;
            }
            if ($request->filled('noTelpIbu')) {
                $updateData['no_telp_ibu'] = $request->noTelpIbu;
            }
            if ($request->filled('namaWali')) {
                $updateData['nama_wali'] = $request->namaWali;
            }
            if ($request->filled('hubunganWali')) {
                $updateData['hubungan_wali'] = $request->hubunganWali;
            }
            if ($request->filled('noTelpWali')) {
                $updateData['no_telp_wali'] = $request->noTelpWali;
            }

            if (empty($updateData)) {
                return $this->response("Tidak ada data yang diperbarui.", Response::HTTP_BAD_REQUEST);
            }

            $siswa->update($updateData);

            return $this->response(
                "Siswa dengan id:{$request->idSiswa} berhasil diupdate.",
                Response::HTTP_ACCEPTED,
                $this->toApiArray($siswa->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validate = Validator::make(['id' => $id], [
                'id' => 'required|exists:siswas,id',
            ], [
                'id.exists' => "Siswa dengan id:{$id} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $siswa = Siswa::withTrashed()->find($id);
            if (!$siswa || $siswa->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $siswa->delete();

            return $this->response(
                "Siswa dengan id:{$id} berhasil dihapus.",
                Response::HTTP_ACCEPTED,
                ['email' => $siswa->email]
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup minimal untuk keperluan internal (Gateway resolve siswa_id dari email)
    public function lookupByEmail(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $siswa = Siswa::where('email', $request->email)->first();
            if (!$siswa) {
                return $this->response('Siswa tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Siswa ditemukan.", Response::HTTP_OK, [
                'idSiswa'     => $siswa->id,
                'namaLengkap' => $siswa->nama_lengkap,
                'email'       => $siswa->email,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup untuk Gateway resolve kartu absensi (scan) -> siswa
    public function lookupByKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'uid' => 'required|string|max:32',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $siswa = Siswa::where('kartu_uid', $request->uid)->first();
            if (!$siswa) {
                return $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Kartu ditemukan.", Response::HTTP_OK, [
                'idSiswa'     => $siswa->id,
                'namaLengkap' => $siswa->nama_lengkap,
                'kartuStatus' => $siswa->kartu_status,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        $map = [
            'id'            => 'idSiswa',
            'nama_lengkap'  => 'namaLengkap',
            'jenis_kelamin' => 'jenisKelamin',
            'tempat_lahir'  => 'tempatLahir',
            'tanggal_lahir' => 'tanggalLahir',
            'tanggal_masuk' => 'tanggalMasuk',
            'status_date'   => 'statusDate',
            'nama_ayah'     => 'namaAyah',
            'nama_ibu'      => 'namaIbu',
            'pekerjaan_ayah'=> 'pekerjaanAyah',
            'pekerjaan_ibu' => 'pekerjaanIbu',
            'no_telp_ayah'  => 'noTelpAyah',
            'no_telp_ibu'   => 'noTelpIbu',
            'nama_wali'     => 'namaWali',
            'hubungan_wali' => 'hubunganWali',
            'no_telp_wali'  => 'noTelpWali',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }
        return $result;
    }

    private function generateUniqueFilename(string $directory, string $extension): string
    {
        do {
            $filename = Carbon::now()->format('Y-m-d')
                . '_' . Str::random(12)
                . '.' . $extension;
        } while (Storage::disk('private')->exists("{$directory}/{$filename}"));

        return $filename;
    }

    private function convertImage(\Illuminate\Http\UploadedFile $file, int $quality = 85): mixed
    {
        return ImageManager::gd()->read($file->getPathname())
            ->orient()
            ->coverDown(360, 480)
            ->toWebp($quality);
    }
}
