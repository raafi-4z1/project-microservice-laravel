<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    use ApiResponser, LogsAudit;

    // SuperAdmin tidak bisa dihapus via API oleh siapapun
    private const UNDELETABLE_ROLES = ['SuperAdmin'];

    // Role yang Admin boleh hapus
    private const ADMIN_DELETABLE_ROLES = ['Guru', 'Siswa', 'Karyawan'];

    /**
     * Administrator Sekolah (staf TU) berrole `Karyawan`, sehingga pemeriksaan
     * berbasis role saja TIDAK menjangkaunya — tanpa ini ia bisa menghapus akun
     * Admin. Batasnya dibuat sama dengan Admin, PLUS larangan menyentuh sesama
     * Administrator Sekolah supaya mereka tidak bisa saling menyingkirkan.
     *
     * null = boleh; JsonResponse = ditolak.
     */
    private function batasiAdminSekolah(User $requester, User $target, string $aksi): ?\Illuminate\Http\JsonResponse
    {
        if (!$requester->isAdminSekolah()) {
            return null;
        }

        if (!in_array($target->role, self::ADMIN_DELETABLE_ROLES, true)) {
            return $this->response(
                "Administrator Sekolah hanya dapat {$aksi} akun dengan role: "
                    . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        if ($target->isAdminSekolah()) {
            return $this->response(
                "Administrator Sekolah tidak dapat {$aksi} akun sesama Administrator Sekolah.",
                Response::HTTP_FORBIDDEN
            );
        }

        return null;
    }

    /**
     * Susun payload update password + penanda wajib-ganti.
     *
     * `Schema::hasColumn` mengikuti konvensi `UserService` dan
     * `ForcePasswordChange`, yang sengaja ditulis agar tetap hidup di deployment
     * yang kodenya sudah ditarik tapi migrasinya belum jalan. Menulis kolom itu
     * tanpa penjaga membuat reset & restore password gagal 500 "Unknown column"
     * di keadaan yang justru sudah diantisipasi bagian kode lain.
     */
    private function payloadPasswordBaru(string $passwordPolos): array
    {
        $payload = ['password' => Hash::make($passwordPolos)];

        if (Schema::hasColumn('users', 'must_change_password')) {
            $payload['must_change_password'] = true;
        }

        return $payload;
    }

    /** Batas atas per_page, seragam dengan endpoint berpaginasi lainnya. */
    private const MAX_PER_PAGE = 200;

    public function index(Request $request)
    {
        // Sebelumnya `per_page` masuk langsung ke paginate() tanpa diperiksa:
        // `per_page=abc` dan `per_page=-5` melempar 500, `per_page=100000`
        // diterima apa adanya. Divalidasi supaya jadi 422 yang bisa dibaca klien.
        $validate = Validator::make($request->all(), [
            'page'     => 'sometimes|numeric|min:1',
            'per_page' => 'sometimes|numeric|min:1|max:' . self::MAX_PER_PAGE,
            'role'     => 'sometimes|string|max:30',
            'search'   => 'sometimes|string|max:100',
        ]);

        if ($validate->fails()) {
            return $this->response(
                $validate->errors()->first(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validate->errors()
            );
        }

        $query = // 'is_admin_sekolah' WAJIB ikut di-select: accessor isAdminSekolah membacanya
        // dari atribut model, jadi kalau kolomnya tidak dimuat hasilnya bukan "hilang"
        // melainkan selalu false — daftar user akan menyatakan tidak ada Administrator
        // Sekolah padahal ada.
        User::select('id', 'name', 'email', 'role', 'is_admin_sekolah', 'is_petugas_acara', 'must_change_password', 'created_at');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Cari di nama atau email
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        // withQueryString: tanpa ini `next_page_url` dan `links[]` kehilangan
        // `per_page`, `role`, dan `search`. Klien yang mengikuti tautannya
        // diam-diam kembali ke 10/halaman DAN kehilangan filternya — melompati
        // baris dan menampilkan data yang sengaja disaring.
        $users = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        return $this->response('Data users.', Response::HTTP_OK, $users);
    }

    public function show($id)
    {
        $user = // 'is_admin_sekolah' WAJIB ikut di-select: accessor isAdminSekolah membacanya
        // dari atribut model, jadi kalau kolomnya tidak dimuat hasilnya bukan "hilang"
        // melainkan selalu false — daftar user akan menyatakan tidak ada Administrator
        // Sekolah padahal ada.
        User::select('id', 'name', 'email', 'role', 'is_admin_sekolah', 'is_petugas_acara', 'must_change_password', 'created_at')->find($id);

        if (!$user) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        return $this->response('Data user.', Response::HTTP_OK, $user);
    }

    public function destroy($id)
    {
        $requester = auth()->user();
        $target = User::find($id);

        if (!$target) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        // Tidak bisa menghapus akun sendiri
        if ($target->id === $requester->id) {
            return $this->response(
                'Tidak dapat menghapus akun sendiri.',
                Response::HTTP_FORBIDDEN
            );
        }

        // SuperAdmin tidak bisa dihapus via API oleh siapapun
        if (in_array($target->role, self::UNDELETABLE_ROLES)) {
            return $this->response(
                'Akun SuperAdmin tidak dapat dihapus melalui API.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Admin hanya boleh menghapus Guru, Siswa, Karyawan
        if ($requester->role === 'Admin' && !in_array($target->role, self::ADMIN_DELETABLE_ROLES)) {
            return $this->response(
                'Admin hanya dapat menghapus akun dengan role: ' . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        if ($tolak = $this->batasiAdminSekolah($requester, $target, 'menghapus')) {
            return $tolak;
        }

        // Cabut semua token aktif milik user yang dihapus
        $target->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

        $target->delete(); // soft delete — data tetap di DB, deleted_at terisi

        $this->auditLog('deleted', 'user', $target->email, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);

        return $this->response('Akun user berhasil dihapus.', Response::HTTP_ACCEPTED, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $requester = User::find(auth()->id());
        $target    = User::find($id);

        if (!$target) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        // Tidak bisa reset password akun sendiri lewat endpoint ini — pakai /password
        if ($target->id === $requester->id) {
            return $this->response(
                'Gunakan endpoint POST /password untuk mengubah password sendiri.',
                Response::HTTP_FORBIDDEN
            );
        }

        // SuperAdmin tidak bisa di-reset passwordnya via API
        if ($target->role === 'SuperAdmin') {
            return $this->response(
                'Password SuperAdmin tidak dapat direset melalui API.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Admin hanya boleh reset password Guru, Siswa, Karyawan
        if ($requester->role === 'Admin' && !in_array($target->role, self::ADMIN_DELETABLE_ROLES)) {
            return $this->response(
                'Admin hanya dapat mereset password: ' . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Reset password = jalan pintas mengambil alih akun, jadi dibatasi sama
        // ketatnya dengan penghapusan (termasuk sesama Administrator Sekolah).
        if ($tolak = $this->batasiAdminSekolah($requester, $target, 'mereset password')) {
            return $tolak;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'new_password'     => ['required', Password::min(8)->letters()->numbers()],
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->response(
                $validator->messages()->first(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->all()
            );
        }

        // Sama seperti restore(): password pilihan admin harus diganti pemiliknya.
        $target->update($this->payloadPasswordBaru($request->new_password));

        // Cabut semua token aktif agar target wajib login ulang
        $target->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

        $this->auditLog('updated', 'user', $target->email, [
            'field'      => 'password',
            'target'     => $target->email,
            'targetRole' => $target->role,
        ]);

        return $this->response('Password user berhasil direset. User harus login ulang.', Response::HTTP_OK, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);
    }

    /**
     * GET /users/terhapus — daftar akun yang di-soft-delete.
     *
     * Ada supaya operator bisa MEMILIH akun yang mau dihidupkan lagi. Tanpa daftar
     * ini satu-satunya cara memulihkan adalah menebak emailnya lewat `register`,
     * dan jalur itu menimpa data lama (lihat `restore()` di bawah).
     *
     * Proyeksinya disusun eksplisit, bukan diserahkan ke serialisasi model:
     * daftar ini dibaca layar "Pulihkan Akun", dan proyeksi eksplisit memastikan
     * kolom baru tidak ikut terbawa diam-diam suatu hari nanti.
     */
    public function terhapus(Request $request)
    {
        // Divalidasi persis seperti index(). Tanpa ini `?search[]=a` membuat
        // array diinterpolasi ke string dan endpoint membalas 500, sementara
        // `/users?search[]=a` yang setara membalas 422 — dua endpoint kembar
        // dengan perilaku galat berbeda.
        $validate = Validator::make($request->all(), [
            'page'     => 'sometimes|numeric|min:1',
            'per_page' => 'sometimes|numeric|min:1|max:' . self::MAX_PER_PAGE,
            'role'     => 'sometimes|string|max:30',
            'search'   => 'sometimes|string|max:100',
        ]);

        if ($validate->fails()) {
            return $this->response(
                $validate->errors()->first(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validate->errors()
            );
        }

        // `is_admin_sekolah`/`is_petugas_acara` WAJIB ikut di-select: keduanya
        // dibaca langsung di bawah, dan kalau kolomnya tidak dimuat hasilnya
        // bukan "hilang" melainkan selalu false — jebakan yang sama persis
        // dengan index() dan show().
        $query = User::onlyTrashed()->select(
            'id', 'name', 'email', 'role',
            'is_admin_sekolah', 'is_petugas_acara', 'deleted_at'
        );

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);

        // Terbaru dihapus di atas: yang barusan salah hapus itulah yang paling
        // mungkin sedang dicari operator.
        // Tie-break `id` wajib: `deleted_at` berpresisi detik, dan menghapus
        // guru/siswa/karyawan ikut menghapus akunnya — jadi penghapusan massal
        // menghasilkan banyak baris berstempel detik yang sama. Tanpa urutan
        // kedua yang pasti, MySQL boleh mengurutkannya berbeda tiap query:
        // satu baris bisa muncul dua kali sementara baris lain tak pernah
        // muncul di halaman mana pun.
        $users = $query->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $users->through(fn (User $u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'role'           => $u->role,
            'isAdminSekolah' => (bool) $u->is_admin_sekolah,
            'isPetugasAcara' => (bool) $u->is_petugas_acara,
            'deletedAt'      => optional($u->deleted_at)->toISOString(),
        ]);

        return $this->response('Daftar akun terhapus.', Response::HTTP_OK, $users);
    }

    /**
     * POST /users/{id}/restore — aktifkan ulang akun APA ADANYA.
     *
     * Bedanya dengan memulihkan lewat `register` email yang sama: jalur itu
     * MENIMPA nama, role, dan password dengan isi form, sehingga operator yang
     * cuma ingin menghidupkan akun bisa tak sengaja mengganti role-nya. Di sini
     * tidak satu pun field lama disentuh; password hanya berubah kalau memang
     * dikirim, karena plaintext lama tidak bisa dikembalikan.
     *
     * Gating SuperAdmin/Admin saja — Administrator Sekolah TIDAK, konsisten
     * dengan `register`: menghidupkan kembali akun Admin yang sudah disingkirkan
     * adalah eskalasi hak yang sama seriusnya dengan membuatnya dari nol.
     */
    public function restore(Request $request, $id)
    {
        // withTrashed tanpa select parsial: seluruh kolom dimuat, jadi accessor
        // penanda aman dan `trashed()` punya deleted_at yang dibutuhkannya.
        $target = User::withTrashed()->find($id);

        if (!$target) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        // PENJAGA ROLE — sempat hilang sama sekali di versi pertama, dan itu
        // lubang eskalasi: `destroy()` melarang Admin menghapus akun
        // Admin/SuperAdmin dan `resetPassword()` melarang Admin mereset
        // passwordnya, tapi `restore()` tanpa penjaga menggabungkan KEDUA
        // kewenangan terlarang itu dalam satu panggilan. Jalurnya nyata:
        // SuperAdmin menghapus akun Admin, lalu Admin lain memanggil
        // restore dengan body {"password":"..."} dan akun Admin itu jadi
        // miliknya.
        $requester = auth()->user();

        // Akun SuperAdmin tidak boleh dihidupkan lagi oleh Admin. (Tidak
        // dikunci total seperti destroy(): SuperAdmin sendiri masih boleh,
        // supaya penghapusan lewat DB tidak jadi jalan buntu permanen.)
        if ($target->role === 'SuperAdmin' && $requester->role !== 'SuperAdmin') {
            return $this->response(
                'Akun SuperAdmin hanya dapat dipulihkan oleh SuperAdmin.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Batas Admin dibuat sama persis dengan destroy() dan resetPassword().
        if ($requester->role === 'Admin' && !in_array($target->role, self::ADMIN_DELETABLE_ROLES, true)) {
            return $this->response(
                'Admin hanya dapat memulihkan akun dengan role: '
                    . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Administrator Sekolah sudah tertahan `check.role` di route, tapi
        // CheckRole hanya MEMBERI izin dan tidak pernah mencabut — kalau suatu
        // saat route-nya diperlebar, lapisan ini yang tetap menahan.
        if ($tolak = $this->batasiAdminSekolah($requester, $target, 'memulihkan')) {
            return $tolak;
        }

        // Aman diulang: memulihkan akun yang sudah aktif bukan kesalahan server,
        // hanya permintaan yang tidak ada artinya.
        if (!$target->trashed()) {
            return $this->response(
                "Akun {$target->email} masih aktif — tidak ada yang perlu dipulihkan.",
                Response::HTTP_CONFLICT,
                ['id' => $target->id, 'email' => $target->email, 'role' => $target->role]
            );
        }

        $gantiPassword = $request->filled('password');
        if ($gantiPassword) {
            $validator = Validator::make($request->all(), [
                'password' => ['required', Password::min(8)->letters()->numbers()],
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->all()
                );
            }
        }

        $target->restore();

        if ($gantiPassword) {
            // Password yang dipilih admin sudah diketahui admin. Biarkan begitu
            // dan kredensial itu dipakai selamanya oleh dua orang. `must_change_password`
            // memaksa pemiliknya menggantinya saat login pertama — mekanisme yang
            // sudah ada (`force.pwd`) dan sudah dipakai `UserService::create()`
            // untuk kasus yang sama persis.
            $target->update($this->payloadPasswordBaru($request->input('password')));
        }

        // Token lama HARUS mati. Ketiga jalur sejenis sudah melakukannya
        // (`destroy()`, `resetPassword()`, dan pemulihan lewat
        // `UserService::create()` yang komentarnya berbunyi "Token sebelum
        // penghapusan harus mati") — `restore()` sempat jadi satu-satunya yang
        // tidak, dan itu bocor dua arah:
        //
        //  1. Token terbitan sebelum penghapusan hidup lagi begitu `deleted_at`
        //     kosong. Sesi yang sudah diputus bangkit tanpa ada yang login.
        //  2. Lebih buruk saat `password` dikirim: maksud menetapkan password
        //     baru adalah mengambil alih akun atau mengunci pemegang lama, tapi
        //     token pemegang lama tetap sah — persis yang dicegah
        //     `resetPassword()`.
        //
        // Mencabut token TIDAK melanggar "pulihkan apa adanya": yang dijaga
        // brief adalah field tersimpan (nama, role, jabatan, password), bukan
        // sesi. Pemiliknya tinggal login ulang.
        $target->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

        $this->auditLog('restored', 'user', $target->email, [
            'name'           => $target->name,
            'email'          => $target->email,
            'role'           => $target->role,
            // JANGAN namai ini *password*: sanitizer auditLog() membuang setiap
            // key yang mengandung kata itu, jadi penandanya akan hilang diam-diam
            // — sudah sempat terjadi. Nilainya boolean, bukan kredensial.
            'kredensialDiganti' => $gantiPassword,
            'tokenDicabut'      => true,
            'via'               => 'users/{id}/restore',
        ]);

        $data = [
            'id'             => $target->id,
            'name'           => $target->name,
            'email'          => $target->email,
            'role'           => $target->role,
            'isAdminSekolah' => $target->isAdminSekolah(),
            'isPetugasAcara' => $target->isPetugasAcara(),
            'passwordDiubah' => $gantiPassword,
            'tokenDicabut'   => true,
            'wajibGantiPassword' => $gantiPassword,
        ];

        // Menghapus guru/siswa/karyawan ikut menghapus akunnya, tapi TIDAK
        // sebaliknya: endpoint ini hanya menyentuh tabel users. Kalau akun ini
        // dulu dihapus lewat DELETE /{modul}/{id}, record domainnya masih
        // terhapus dan endpoint layan-diri (mis. rekap/pegawai/saya) membalas
        // 404 — bukan 403. Disebut di sini supaya tidak didiagnosis sebagai bug
        // izin, kesalahpahaman yang sudah pernah terjadi.
        if (in_array($target->role, ['Guru', 'Siswa', 'Karyawan'], true)) {
            $modul = $this->modulDomain($target->role);
            $data['catatan'] = 'Hanya akun login yang dipulihkan, bukan record domainnya. '
                . "Bila akun ini dulu dihapus lewat DELETE /{$modul}/{id}, JANGAN pakai endpoint ini: "
                . "panggil POST /{$modul} dengan email yang sama selagi akunnya masih terhapus — "
                . 'satu panggilan itu memulihkan record domain DAN akunnya sekaligus, dengan id lama. '
                . "Kalau terlanjur dipulihkan di sini, hapus lagi akunnya (DELETE /users/{$target->id}) "
                . "lalu jalankan POST /{$modul}; selama akunnya aktif, POST /{$modul} ditolak 422 "
                . 'karena emailnya dianggap milik akun yang masih hidup.';
        }

        $pesan = $gantiPassword
            ? 'Akun dipulihkan apa adanya; password diganti sesuai permintaan. Sesi lama dicabut — user harus login ulang.'
            : 'Akun dipulihkan apa adanya — nama, role, dan password lama tidak diubah. Sesi lama dicabut — user harus login ulang.';

        return $this->response($pesan, Response::HTTP_OK, $data);
    }

    /** Peta role ke prefix modul domain, dipakai pesan petunjuk di restore(). */
    private function modulDomain(string $role): string
    {
        return match ($role) {
            'Guru'     => 'guru',
            'Siswa'    => 'siswa',
            'Karyawan' => 'karyawan',
            default    => 'modul',
        };
    }
}
