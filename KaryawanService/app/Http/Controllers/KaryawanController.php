<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class KaryawanController extends Controller
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

            $columns  = ['id', 'nama_lengkap', 'nip', 'email', 'jabatan', 'status_kepegawaian', 'kartu_status'];
            $perPage  = $request->input('per_page', 5);

            $query = Karyawan::select($columns);

            // Cari di nama, NIP, email, atau jabatan
            if ($request->filled('search')) {
                $s = $request->input('search');
                $query->where(function ($q) use ($s) {
                    $q->where('nama_lengkap', 'like', "%{$s}%")
                      ->orWhere('nip', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                      ->orWhere('jabatan', 'like', "%{$s}%");
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

            return $this->response("List Data Karyawan.", Response::HTTP_OK, $pageArr);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idKaryawan' => 'required|exists:karyawans,id',
            ], [
                'idKaryawan.exists' => "Karyawan dengan id:{$request->idKaryawan} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $karyawan = Karyawan::find($request->idKaryawan);
            if ($karyawan === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            return $this->response(
                "Karyawan dengan id:{$request->idKaryawan}.",
                Response::HTTP_OK,
                $this->toApiArray($karyawan->toArray())
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
                'email'             => 'required|email',
                'nip'               => 'required|string|max:20',
                'namaLengkap'       => 'required',
                'jabatan'           => 'required',
                'statusKepegawaian' => 'sometimes',
                'jenisKelamin'      => 'sometimes|in:Laki-Laki,Perempuan',
                'noTelp'            => 'sometimes|numeric',
                'alamat'            => 'sometimes',
                'foto'              => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $data = [
                'email'        => $request->email,
                'nip'          => $request->nip,
                'nama_lengkap' => $request->namaLengkap,
                'jabatan'      => $request->jabatan,
            ];

            if ($request->filled('statusKepegawaian')) {
                $data['status_kepegawaian'] = $request->statusKepegawaian;
            }
            if ($request->filled('jenisKelamin')) {
                $data['jenis_kelamin'] = $request->jenisKelamin;
            }
            if ($request->filled('noTelp')) {
                $data['no_telp'] = $request->noTelp;
            }
            if ($request->filled('alamat')) {
                $data['alamat'] = $request->alamat;
            }
            if ($request->hasFile('foto')) {
                $directoryStorage = 'profiles';
                $filename = $this->generateUniqueFilename($directoryStorage, 'webp');
                $path     = "{$directoryStorage}/{$filename}";
                Storage::disk('private')->put($path, (string) $this->convertImage($request->file('foto')));
                $data['foto'] = $path;
            }

            $karyawan = Karyawan::create($data);

            return $this->response(
                "Data Karyawan berhasil disimpan.",
                Response::HTTP_CREATED,
                ['idKaryawan' => $karyawan->id]
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
                'idKaryawan'        => 'required|exists:karyawans,id',
                'nip'               => 'sometimes|string|max:20',
                'namaLengkap'       => 'sometimes',
                'jabatan'           => 'sometimes',
                'statusKepegawaian' => 'sometimes',
                'jenisKelamin'      => 'sometimes|in:Laki-Laki,Perempuan',
                'noTelp'            => 'sometimes|numeric',
                'alamat'            => 'sometimes',
                'foto'              => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
            ], [
                'idKaryawan.exists' => "Karyawan dengan id:{$request->idKaryawan} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $karyawan = Karyawan::find($request->idKaryawan);
            if ($karyawan === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $updateData = [];
            if ($request->filled('nip')) {
                $updateData['nip'] = $request->nip;
            }
            if ($request->filled('namaLengkap')) {
                $updateData['nama_lengkap'] = $request->namaLengkap;
            }
            if ($request->filled('jabatan')) {
                $updateData['jabatan'] = $request->jabatan;
            }
            if ($request->filled('statusKepegawaian')) {
                $updateData['status_kepegawaian'] = $request->statusKepegawaian;
            }
            if ($request->filled('jenisKelamin')) {
                $updateData['jenis_kelamin'] = $request->jenisKelamin;
            }
            if ($request->filled('noTelp')) {
                $updateData['no_telp'] = $request->noTelp;
            }
            if ($request->filled('alamat')) {
                $updateData['alamat'] = $request->alamat;
            }
            if ($request->hasFile('foto')) {
                $directoryStorage = 'profiles';
                $filename = $this->generateUniqueFilename($directoryStorage, 'webp');
                $newPath  = "{$directoryStorage}/{$filename}";
                Storage::disk('private')->put($newPath, (string) $this->convertImage($request->file('foto')));

                $rawFoto = $karyawan->getRawOriginal('foto');
                if ($rawFoto && Storage::disk('private')->exists($rawFoto)) {
                    Storage::disk('private')->delete($rawFoto);
                }
                $updateData['foto'] = $newPath;
            }

            if (empty($updateData)) {
                return $this->response("Tidak ada data yang diperbarui.", Response::HTTP_BAD_REQUEST);
            }

            $karyawan->update($updateData);

            return $this->response(
                "Karyawan dengan id:{$request->idKaryawan} berhasil diupdate.",
                Response::HTTP_ACCEPTED,
                $this->toApiArray($karyawan->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validate = Validator::make(['id' => $id], [
                'id' => 'required|exists:karyawans,id',
            ], [
                'id.exists' => "Karyawan dengan id:{$id} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $karyawan = Karyawan::withTrashed()->find($id);
            if (!$karyawan || $karyawan->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $karyawan->delete();

            return $this->response(
                "Karyawan dengan id:{$id} berhasil dihapus.",
                Response::HTTP_ACCEPTED,
                ['email' => $karyawan->email]
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup minimal untuk Gateway resolve karyawan_id dari email
    public function lookupByEmail(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $karyawan = Karyawan::where('email', $request->email)->first();
            if (!$karyawan) {
                return $this->response('Karyawan tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Karyawan ditemukan.", Response::HTTP_OK, [
                'idKaryawan'  => $karyawan->id,
                'namaLengkap' => $karyawan->nama_lengkap,
                'email'       => $karyawan->email,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup untuk Gateway resolve kartu absensi (scan) -> karyawan
    public function lookupByKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'uid' => 'required|string|max:32',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $karyawan = Karyawan::where('kartu_uid', $request->uid)->first();
            if (!$karyawan) {
                return $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Kartu ditemukan.", Response::HTTP_OK, [
                'idKaryawan'  => $karyawan->id,
                'namaLengkap' => $karyawan->nama_lengkap,
                'kartuStatus' => $karyawan->kartu_status,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        $map = [
            'id'                   => 'idKaryawan',
            'nama_lengkap'         => 'namaLengkap',
            'status_kepegawaian'   => 'statusKepegawaian',
            'jenis_kelamin'        => 'jenisKelamin',
            'no_telp'              => 'noTelp',
            'kartu_uid'            => 'kartuUid',
            'kartu_status'         => 'kartuStatus',
            'kartu_diterbitkan_at' => 'kartuDiterbitkanAt',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }
        return $result;
    }

    private function convertImage(\Illuminate\Http\UploadedFile $file, int $quality = 85): mixed
    {
        return ImageManager::gd()->read($file->getPathname())
            ->orient()
            ->coverDown(360, 480)
            ->toWebp($quality);
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
}
