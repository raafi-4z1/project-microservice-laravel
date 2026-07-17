<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class GuruController extends Controller
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

            $columns  = ['id', 'nama_lengkap', 'nip', 'email', 'jabatan', 'status_kepegawaian'];
            $perPage  = $request->input('per_page', 5);

            $query = Guru::select($columns);

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

            return $this->response("List Data Guru.", Response::HTTP_OK, $pageArr);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idGuru' => 'required|exists:gurus,id',
            ], [
                'idGuru.exists' => "Guru dengan id:{$request->idGuru} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::find($request->idGuru);
            if ($guru === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            return $this->response(
                "Guru dengan id:{$request->idGuru}.",
                Response::HTTP_OK,
                $this->toApiArray($guru->toArray())
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
                'email'              => 'required|email',
                'nik'                => 'required|numeric',
                'nip'                => 'required|numeric',
                'namaLengkap'        => 'required',
                'telephone'          => 'required|numeric',
                'jenisKelamin'       => 'required|in:Laki-Laki,Perempuan',
                'tempatLahir'        => 'required',
                'tanggalLahir'       => 'required|date',
                'agama'              => 'sometimes',
                'statusPernikahan'   => 'sometimes',
                'alamat'             => 'required',
                'foto'               => [
                    'required', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
                'statusKepegawaian'  => 'required',
                'nomorSKPengangkatan'=> 'sometimes|numeric',
                'tanggalMasuk'       => 'required|date',
                'jabatan'            => 'required',
                'nomorSertifikasi'   => 'sometimes|numeric',
                'pendidikanTerakhir' => 'required',
                'jurusan'            => 'required',
                'universitas'        => 'required',
                'tahunLulus'         => 'required',
                'pelatihan'          => 'sometimes',
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
                'email'             => $request->email,
                'nik'               => $request->nik,
                'nip'               => $request->nip,
                'nama_lengkap'      => $request->namaLengkap,
                'telephone'         => $request->telephone,
                'jenis_kelamin'     => $request->jenisKelamin,
                'tempat_lahir'      => $request->tempatLahir,
                'tanggal_lahir'     => $request->tanggalLahir,
                'alamat'            => $request->alamat,
                'foto'              => $path,
                'status_kepegawaian'=> $request->statusKepegawaian,
                'tanggal_masuk'     => $request->tanggalMasuk,
                'jabatan'           => $request->jabatan,
                'pendidikan_terakhir'=> $request->pendidikanTerakhir,
                'jurusan'           => $request->jurusan,
                'universitas'       => $request->universitas,
                'tahun_lulus'       => $request->tahunLulus,
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

            $guru = Guru::create($data);

            return $this->response(
                "Data Guru berhasil disimpan.",
                Response::HTTP_CREATED,
                ['idGuru' => $guru->id]
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
                'idGuru'             => 'required|exists:gurus,id',
                'nik'                => 'sometimes|numeric',
                'nip'                => 'sometimes|numeric',
                'namaLengkap'        => 'sometimes',
                'telephone'          => 'sometimes|numeric',
                'jenisKelamin'       => 'sometimes|in:Laki-Laki,Perempuan',
                'tempatLahir'        => 'sometimes',
                'tanggalLahir'       => 'sometimes|date',
                'agama'              => 'sometimes',
                'statusPernikahan'   => 'sometimes',
                'alamat'             => 'sometimes',
                'foto'               => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480',
                ],
                'statusKepegawaian'  => 'sometimes',
                'nomorSKPengangkatan'=> 'sometimes|numeric',
                'tanggalMasuk'       => 'sometimes|date',
                'jabatan'            => 'sometimes',
                'nomorSertifikasi'   => 'sometimes|numeric',
                'pendidikanTerakhir' => 'sometimes',
                'jurusan'            => 'sometimes',
                'universitas'        => 'sometimes',
                'tahunLulus'         => 'sometimes',
                'pelatihan'          => 'sometimes',
            ], [
                'idGuru.exists' => "Guru dengan id:{$request->idGuru} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::find($request->idGuru);
            if ($guru === null) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
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
            if ($request->hasFile('foto')) {
                $directoryStorage = 'profiles';
                $filename = $this->generateUniqueFilename($directoryStorage, 'webp');
                $newPath  = "{$directoryStorage}/{$filename}";
                Storage::disk('private')->put($newPath, (string) $this->convertImage($request->file('foto')));

                // getRawOriginal: ambil PATH asli, bukan accessor foto (base64)
                $oldFoto = $guru->getRawOriginal('foto');
                if ($oldFoto && Storage::disk('private')->exists($oldFoto)) {
                    Storage::disk('private')->delete($oldFoto);
                }
                $updateData['foto'] = $newPath;
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
                return $this->response("Tidak ada data yang diperbarui.", Response::HTTP_BAD_REQUEST);
            }

            $guru->update($updateData);

            return $this->response(
                "Guru dengan id:{$request->idGuru} berhasil diupdate.",
                Response::HTTP_ACCEPTED,
                $this->toApiArray($guru->fresh()->toArray())
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validate = Validator::make(['id' => $id], [
                'id' => 'required|exists:gurus,id',
            ], [
                'id.exists' => "Guru dengan id:{$id} tidak ada di database.",
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $guru = Guru::withTrashed()->find($id);
            if (!$guru || $guru->trashed()) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $guru->delete();

            return $this->response(
                "Guru dengan id:{$id} berhasil dihapus.",
                Response::HTTP_ACCEPTED,
                ['email' => $guru->email]
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup minimal untuk keperluan internal (Gateway resolve guru_id dari email)
    public function lookupByEmail(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::where('email', $request->email)->first();
            if (!$guru) {
                return $this->response('Guru tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Guru ditemukan.", Response::HTTP_OK, [
                'idGuru'      => $guru->id,
                'namaLengkap' => $guru->nama_lengkap,
                'email'       => $guru->email,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Lookup untuk Gateway resolve kartu absensi (scan) -> guru
    public function lookupByKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'uid' => 'required|string|max:32',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::where('kartu_uid', $request->uid)->first();
            if (!$guru) {
                return $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND);
            }

            return $this->response("Kartu ditemukan.", Response::HTTP_OK, [
                'idGuru'      => $guru->id,
                'namaLengkap' => $guru->nama_lengkap,
                'kartuStatus' => $guru->kartu_status,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Terbitkan/ganti kartu absensi: generate UID unik (prefix GUR-), set aktif.
    public function terbitkanKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idGuru' => 'required|exists:gurus,id',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::find($request->idGuru);
            if (!$guru) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            do {
                $uid = 'GUR-' . strtoupper(Str::random(12));
            } while (Guru::where('kartu_uid', $uid)->exists());

            $guru->update([
                'kartu_uid'            => $uid,
                'kartu_status'         => 'aktif',
                'kartu_diterbitkan_at' => now(),
            ]);

            return $this->response("Kartu diterbitkan.", Response::HTTP_OK, [
                'idGuru'            => $guru->id,
                'kartuUid'          => $uid,
                'kartuStatus'       => 'aktif',
                'kartuDiterbitkanAt'=> $guru->kartu_diterbitkan_at,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Blokir kartu (hilang/blokir) tanpa menerbitkan yang baru -> scan ditolak.
    public function blokirKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idGuru' => 'required|exists:gurus,id',
                'status' => 'sometimes|in:hilang,blokir',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::find($request->idGuru);
            if (!$guru) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }
            if (!$guru->kartu_uid) {
                return $this->response("Guru belum memiliki kartu.", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $status = $request->input('status', 'hilang');
            $guru->update(['kartu_status' => $status]);

            return $this->response("Kartu diblokir ({$status}).", Response::HTTP_OK, [
                'idGuru'      => $guru->id,
                'kartuStatus' => $status,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Atur/ganti PIN absensi (dipakai saat lupa kartu). Disimpan ter-hash.
    public function setPin(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idGuru' => 'required|exists:gurus,id',
                'pin'    => ['required', 'regex:/^\d{4,6}$/'],
            ], [
                'pin.regex' => 'PIN harus 4-6 digit angka.',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::find($request->idGuru);
            if (!$guru) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $guru->update(['pin_hash' => Hash::make($request->pin)]);

            return $this->response("PIN absensi berhasil diatur.", Response::HTTP_OK, ['idGuru' => $guru->id]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Verifikasi NIP + PIN (dipanggil Gateway saat absen via PIN). Tidak membocorkan hash.
    public function verifyPin(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'nip' => 'required|string',
                'pin' => 'required|string',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $guru = Guru::where('nip', $request->nip)->first();
            if (!$guru) {
                return $this->response('Pegawai tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }
            if (!$guru->pin_hash) {
                return $this->response('PIN belum diatur.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if (!Hash::check($request->pin, $guru->pin_hash)) {
                return $this->response('PIN salah.', Response::HTTP_UNAUTHORIZED);
            }

            return $this->response('PIN valid.', Response::HTTP_OK, [
                'idGuru'      => $guru->id,
                'namaLengkap' => $guru->nama_lengkap,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function toApiArray(array $data): array
    {
        $map = [
            'id'                   => 'idGuru',
            'nama_lengkap'         => 'namaLengkap',
            'jenis_kelamin'        => 'jenisKelamin',
            'tempat_lahir'         => 'tempatLahir',
            'tanggal_lahir'        => 'tanggalLahir',
            'status_kepegawaian'   => 'statusKepegawaian',
            'tanggal_masuk'        => 'tanggalMasuk',
            'pendidikan_terakhir'  => 'pendidikanTerakhir',
            'tahun_lulus'          => 'tahunLulus',
            'status_pernikahan'    => 'statusPernikahan',
            'nomor_sk_pengangkatan'=> 'nomorSKPengangkatan',
            'nomor_sertifikasi'    => 'nomorSertifikasi',
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
