<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
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

class KaryawanController extends Controller
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
                // Batas per_page berbeda antara dua mode; lihat komentar di bawah.
                'per_page' => 'sometimes|numeric|min:1|max:200',
                'foto'     => 'sometimes|in:0,1',
                'search'   => 'sometimes|string|max:100',
            ]);

            if ($validate->fails()) {
                return $this->response(
                    $validate->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validate->errors()
                );
            }

            $columns  = ['id', 'nama_lengkap', 'nip', 'email', 'jabatan', 'is_admin_sekolah', 'status_kepegawaian', 'kartu_status'];
            // DUA MODE yang saling tarik:
            //  - foto=1  (daftar-UI): tiap baris membawa foto -> halaman KECIL
            //            (maks 25, default 5) supaya tak membebani server.
            //  - tanpa foto (cache nama): halaman BESAR (maks 200) supaya klien
            //            bisa memuat banyak nama sekaligus.
            // Untuk memuat SELURUH nama sekolah besar, pakai endpoint /nama.
            $modeFoto = (string) $request->input('foto', '0') === '1';
            $maksPerPage = $modeFoto ? 25 : 200;
            $perPage = (int) $request->input('per_page', $modeFoto ? 5 : 5);
            if ($perPage < 1) { $perPage = 1; }
            if ($perPage > $maksPerPage) {
                return $this->response(
                    "per_page maksimum {$maksPerPage}" . ($modeFoto ? ' saat foto=1 (foto berat).' : '.'),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

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
            // Mode foto: tandai baris mana yang PUNYA foto. Kolom `foto` sengaja
            // TIDAK di-select lewat model — accessor-nya mengubah path jadi Base64,
            // persis beban yang ingin dihindari di daftar. DB::table melewati
            // accessor sehingga yang terbaca tetap path mentah.
            //
            // Yang dikirim hanya penanda boolean, bukan path: tata letak
            // penyimpanan bukan urusan klien, dan Gateway-lah yang menyusun URL-nya.
            if ($modeFoto) {
                $ids = array_column($pageArr['data'], 'idKaryawan');
                $punya = empty($ids)
                    ? collect()
                    : \Illuminate\Support\Facades\DB::table('karyawans')->whereIn('id', $ids)->pluck('foto', 'id');

                $pageArr['data'] = array_map(function ($row) use ($punya) {
                    $row['punyaFoto'] = !empty($punya[$row['idKaryawan'] ?? null] ?? null);
                    return $row;
                }, $pageArr['data']);
            }

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
                // `email` & `nip` unik di level DB. Tanpa aturan unique di sini,
                // duplikatnya lolos validasi lalu meledak sebagai 500 dari driver DB
                // alih-alih 422 yang bisa ditampilkan ke pengguna.
                'email'             => ['required', 'email', \Illuminate\Validation\Rule::unique('karyawans', 'email')->whereNull('deleted_at')],
                'nip'               => 'required|string|max:20|unique:karyawans,nip',
                'namaLengkap'       => 'required',
                'jabatan'           => 'required',
                'isAdminSekolah'    => 'sometimes|boolean',
                'statusKepegawaian' => 'sometimes',
                'jenisKelamin'      => 'sometimes|in:Laki-Laki,Perempuan',
                'noTelp'            => 'sometimes|numeric',
                'alamat'            => 'sometimes',
                'foto'              => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    // max_* menutup decompression bomb: `max:2048` membatasi ukuran BERKAS,
                    // bukan ukuran GAMBAR. PNG warna solid 20000x20000 px terkompresi
                    // jadi ratusan KB, lolos batas 2 MB, lalu GD harus men-decode-nya
                    // (20000*20000*4 = 1,6 GB) sebelum coverDown() sempat mengecilkan —
                    // PHP mati kehabisan memori. Aturan `dimensions` memakai getimagesize()
                    // yang hanya membaca header, jadi penolakan terjadi SEBELUM decode.
                    'dimensions:min_width=360,min_height=480,max_width=6000,max_height=6000',
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
                'is_admin_sekolah' => filter_var($request->input('isAdminSekolah', false), FILTER_VALIDATE_BOOLEAN),
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

            // Email yang dipegang baris TERHAPUS dipulihkan, bukan ditolak.
            //
            // `karyawans.email` unik di level DB dan model memakai soft delete, jadi
            // baris yang "dihapus" tetap menahan emailnya selamanya. Sebelumnya itu
            // membuat email tak bisa dipakai ulang sama sekali — masalah nyata di
            // sekolah: siswa pindah lalu kembali, atau akun salah ketik terlanjur
            // dihapus. Memulihkan baris lama sekaligus menjaga riwayat akademiknya
            // (nilai, kelas, absensi) tetap tertaut, yang justru hilang kalau
            // dibuatkan baris baru.
            //
            // `nip`/`nik`/`nisn` SENGAJA tetap unique penuh: kalau nomor itu dipegang
            // baris lain — termasuk yang terhapus — datanya memang berbeda orang,
            // dan menimpanya diam-diam jauh lebih berbahaya daripada menolak.
            $dipulihkan = false;
            $karyawan = Karyawan::onlyTrashed()->where('email', $request->email)->first();
            if ($karyawan) {
                $karyawan->restore();
                $karyawan->update($data);
                $dipulihkan = true;
            } else {
                $karyawan = Karyawan::create($data);
            }

            // `dipulihkan` diteruskan ke Gateway agar jejak auditnya jujur:
            // memulihkan record lama berbeda dari membuat yang baru — riwayat
            // akademiknya ikut hidup kembali, dan itu perlu terlihat di log.
            return $this->response(
                $dipulihkan
                    ? "Data Karyawan dipulihkan dari record yang sebelumnya dihapus."
                    : "Data Karyawan berhasil disimpan.",
                Response::HTTP_CREATED,
                ['idKaryawan' => $karyawan->id, 'dipulihkan' => $dipulihkan]
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
                'isAdminSekolah'    => 'sometimes|boolean',
                'statusKepegawaian' => 'sometimes',
                'jenisKelamin'      => 'sometimes|in:Laki-Laki,Perempuan',
                'noTelp'            => 'sometimes|numeric',
                'alamat'            => 'sometimes',
                'foto'              => [
                    'sometimes', 'file', 'image',
                    'mimes:jpeg,png,jpg,gif', 'max:2048', 'bail',
                    'dimensions:min_width=360,min_height=480,max_width=6000,max_height=6000',
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
            // has(), bukan filled(): "false" harus bisa MENCABUT penandanya.
            // Gateway sudah menyaring siapa yang boleh mengirim field ini.
            if ($request->has('isAdminSekolah')) {
                $updateData['is_admin_sekolah'] = filter_var($request->input('isAdminSekolah'), FILTER_VALIDATE_BOOLEAN);
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
                // `email` sengaja TIDAK bisa diubah lewat endpoint ini: ia kunci
                // penghubung antara akun login (Gateway `users`) dan record domain.
                // Seluruh endpoint layan-diri meresolusi lewat email (`/lookup?email=`,
                // dipakai `rekap/pegawai/saya`, `siswa/saya`, scoping guru), dan tidak
                // ada transaksi lintas-service — mengubahnya di satu sisi saja akan
                // memutus tautan itu tanpa ada yang gagal.
                //
                // Dulu pesannya generik "Tidak ada data yang diperbarui", sehingga
                // pemanggil yang memang bermaksud mengganti email mengira request-nya
                // yang salah bentuk, bukan bahwa field-nya memang immutable.
                return $this->response(
                    $request->filled('email')
                        ? "Email tidak dapat diubah di sini. Tidak ada data lain yang diperbarui."
                        : "Tidak ada data yang diperbarui.",
                    Response::HTTP_BAD_REQUEST
                );
            }

            $karyawan->update($updateData);

            // Jangan balas "berhasil diupdate" polos saat pemanggil mengirim email
            // BERBEDA — field lain memang tersimpan, tapi emailnya diam-diam
            // diabaikan dan pemanggil berhak tahu.
            $pesanSukses = "Karyawan dengan id:{$request->idKaryawan} berhasil diupdate.";
            if ($request->filled('email')
                && strcasecmp(trim((string) $request->email), (string) $karyawan->email) !== 0) {
                $pesanSukses .= " Email TIDAK ikut diubah — email adalah kunci penghubung ke akun login.";
            }

            return $this->response(
                $pesanSukses,
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

    // Terbitkan/ganti kartu absensi: generate UID unik (prefix KAR-), set aktif.
    public function terbitkanKartu(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'idKaryawan' => 'required|exists:karyawans,id',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $karyawan = Karyawan::find($request->idKaryawan);
            if (!$karyawan) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            do {
                $uid = 'KAR-' . strtoupper(Str::random(12));
            } while (Karyawan::where('kartu_uid', $uid)->exists());

            $karyawan->update([
                'kartu_uid'            => $uid,
                'kartu_status'         => 'aktif',
                'kartu_diterbitkan_at' => now(),
            ]);

            return $this->response("Kartu diterbitkan.", Response::HTTP_OK, [
                'idKaryawan'        => $karyawan->id,
                'kartuUid'          => $uid,
                'kartuStatus'       => 'aktif',
                'kartuDiterbitkanAt'=> $karyawan->kartu_diterbitkan_at,
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
                'idKaryawan' => 'required|exists:karyawans,id',
                'status'     => 'sometimes|in:hilang,blokir',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $karyawan = Karyawan::find($request->idKaryawan);
            if (!$karyawan) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }
            if (!$karyawan->kartu_uid) {
                return $this->response("Karyawan belum memiliki kartu.", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $status = $request->input('status', 'hilang');
            $karyawan->update(['kartu_status' => $status]);

            return $this->response("Kartu diblokir ({$status}).", Response::HTTP_OK, [
                'idKaryawan'  => $karyawan->id,
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
                'idKaryawan' => 'required|exists:karyawans,id',
                'pin'        => ['required', 'regex:/^\d{4,6}$/'],
            ], [
                'pin.regex' => 'PIN harus 4-6 digit angka.',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            $karyawan = Karyawan::find($request->idKaryawan);
            if (!$karyawan) {
                return $this->response("Data sudah dihapus.", Response::HTTP_NOT_FOUND);
            }

            $karyawan->update(['pin_hash' => Hash::make($request->pin)]);

            return $this->response("PIN absensi berhasil diatur.", Response::HTTP_OK, ['idKaryawan' => $karyawan->id]);
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

            $karyawan = Karyawan::where('nip', $request->nip)->first();
            if (!$karyawan) {
                return $this->response('Pegawai tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }
            if (!$karyawan->pin_hash) {
                return $this->response('PIN belum diatur.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if (!Hash::check($request->pin, $karyawan->pin_hash)) {
                return $this->response('PIN salah.', Response::HTTP_UNAUTHORIZED);
            }

            return $this->response('PIN valid.', Response::HTTP_OK, [
                'idKaryawan'  => $karyawan->id,
                'namaLengkap' => $karyawan->nama_lengkap,
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
            'is_admin_sekolah'     => 'isAdminSekolah',
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

            $q = Karyawan::withTrashed()->select('id', 'nama_lengkap');

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

            $rows = $q->orderBy('nama_lengkap')->get()->map(fn($r) => [
                'idKaryawan'   => $r->id,
                'namaLengkap' => $r->nama_lengkap,
            ])->all();

            return $this->response('Daftar nama.', Response::HTTP_OK, $rows);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /{prefix}/foto/{id} — kirim berkas foto apa adanya (bukan Base64).
     *
     * Brief menyarankan URL gaya `/storage/...`, tapi foto disimpan di disk
     * `private` dan TIDAK dilayani langsung web server — disengaja: memindahkannya
     * ke disk publik membuat foto setiap siswa bisa diambil siapa saja yang
     * menebak URL-nya. Karena itu URL-nya menunjuk ke endpoint ini, yang tetap
     * melewati autentikasi + gating role Gateway seperti endpoint lain.
     *
     * Klien (Coil/OkHttp) cukup mengirim header Authorization seperti biasa.
     */
    public function foto(Request $request, $id)
    {
        try {
            $row = Karyawan::withTrashed()->find($id);
            if (!$row) {
                return $this->response('Data tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            // getRawOriginal: lewati accessor yang mengubah path menjadi Base64.
            $path = $row->getRawOriginal('foto');
            if (!$path || !Storage::disk('private')->exists($path)) {
                return $this->response('Foto tidak tersedia.', Response::HTTP_NOT_FOUND);
            }

            return response(Storage::disk('private')->get($path), 200)
                ->header('Content-Type', Storage::disk('private')->mimeType($path) ?: 'image/webp')
                // Foto jarang berubah dan berat; biarkan klien menyimpannya.
                // `private` supaya proxy bersama tidak ikut menyimpan.
                ->header('Cache-Control', 'private, max-age=86400');
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
