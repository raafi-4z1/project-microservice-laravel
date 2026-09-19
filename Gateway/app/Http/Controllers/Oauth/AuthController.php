<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponser, LogsAudit;

    // Peran yang boleh dibuat lewat API
    private const REGISTERABLE_ROLES = ['Admin', 'Guru', 'Siswa', 'Karyawan'];

    // Admin hanya boleh membuat peran non-Admin
    private const ADMIN_ALLOWED_ROLES = ['Guru', 'Siswa', 'Karyawan'];

    function register(Request $request) {
        try {
            $requester = Auth::user();

            $allowedRoles = $requester->role === 'SuperAdmin'
                ? self::REGISTERABLE_ROLES
                : self::ADMIN_ALLOWED_ROLES;

            $validator = Validator::make($request->all(), [
                'name'             => 'required',
                // `unique` bawaan ikut menghitung baris terhapus. Di sini yang
                // menghalangi hanya akun AKTIF — email milik akun terhapus
                // dipulihkan lewat UserService::create(), bukan ditolak.
                'email'            => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->whereNull('deleted_at')],
                'password'         => ['required', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'confirm_password' => 'required|same:password',
                'role'             => ['required', 'in:' . implode(',', $allowedRoles)],
                'isPetugasAcara'   => 'sometimes|boolean',
            ], [
                'role.in' => "Role tidak valid. {$requester->role} hanya boleh membuat: " . implode(', ', $allowedRoles) . '.',
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->all()
                );
            }

            // Penanda Petugas Acara hanya boleh diberikan SuperAdmin/Admin —
            // alasan yang sama dengan isAdminSekolah: kalau pemegang penanda bisa
            // memberikannya sendiri, ia bisa mencetak akun baru sesuka hati.
            // Administrator Sekolah boleh mendaftarkan user, tapi TIDAK boleh
            // menyalakan penanda ini.
            $bolehSetPenanda = in_array($requester->role, ['SuperAdmin', 'Admin'], true);
            $petugasAcara = $bolehSetPenanda
                && filter_var($request->input('isPetugasAcara', false), FILTER_VALIDATE_BOOLEAN);

            // Lewat model langsung (bukan UserService) karena register menentukan
            // password sendiri, sementara UserService memakai email sebagai password
            // awal. Jalur pemulihannya disalin di sini agar perilakunya sama.
            $terhapus = User::onlyTrashed()->where('email', $request->email)->first();
            $dipulihkan = false;

            if ($terhapus) {
                $terhapus->restore();
                // Penanda ditulis lewat array dinamis + Schema::hasColumn,
                // mengikuti konvensi UserService/ForcePasswordChange yang sengaja
                // dibuat tahan terhadap deployment yang kodenya sudah ditarik tapi
                // migrasinya belum jalan. Tanpa penjaga, pendaftaran ulang email
                // terhapus gagal 500 "Unknown column" justru di keadaan yang sudah
                // diantisipasi bagian kode lain.
                $timpa = [
                    'name'             => $request->name,
                    'password'         => $request->password,
                    'role'             => $request->role,
                    'is_petugas_acara' => $petugasAcara,
                ];

                // WAJIB ikut ditimpa. Penanda Administrator Sekolah TIDAK pernah
                // diberikan lewat register — asalnya hanya record karyawan.
                // Sebelumnya field ini tidak disentuh sama sekali, sehingga
                // menghapus akun Adm. Sekolah lalu mendaftarkan ulang emailnya
                // sebagai Karyawan biasa menghasilkan akun yang tetap
                // `isAdminSekolah: true` — hak manajemen akademik berpindah ke
                // orang baru tanpa ada yang memberikannya. Terbukti direproduksi.
                if (Schema::hasColumn('users', 'is_admin_sekolah')) {
                    $timpa['is_admin_sekolah'] = false;
                }

                // Kelas yang sama: penanda wajib-ganti milik pemilik LAMA.
                // Nilainya `false`, sama dengan cabang `User::create()` di bawah —
                // pada `register` password memang ditentukan admin secara eksplisit.
                // Beda dengan `resetPassword()`/`restore()` yang memaksanya `true`:
                // di sana akunnya milik orang yang sudah ada dan kredensial pilihan
                // admin tidak boleh menetap. Tanpa baris ini pemilik baru login
                // sukses lalu terdampar di layar ganti-password padahal admin baru
                // saja menetapkan passwordnya.
                if (Schema::hasColumn('users', 'must_change_password')) {
                    $timpa['must_change_password'] = false;
                }

                $terhapus->update($timpa);
                // Token sebelum penghapusan harus mati: akun ini kini milik
                // pendaftaran yang baru, bukan pemilik lamanya.
                $terhapus->tokens()->where('revoked', false)->each(fn($t) => $t->revoke());
                $user = $terhapus;
                $dipulihkan = true;
            } else {
                $user = User::create([
                    'name'             => $request->name,
                    'email'            => $request->email,
                    'password'         => $request->password,
                    'role'             => $request->role,
                    'is_petugas_acara' => $petugasAcara,
                ]);
            }

            // Aksinya dibedakan: memulihkan akun terhapus bukan hal yang sama
            // dengan mendaftarkan yang baru — identitas lama hidup kembali.
            $this->auditLog($dipulihkan ? 'restored' : 'registered', 'user', $user->email, [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'dipulihkan' => $dipulihkan ? 'true' : 'false',
                // Pemberian hak istimewa harus terekam sejak awal, bukan hanya
                // saat diubah.
                'isPetugasAcara' => $petugasAcara ? 'true' : 'false',
            ]);

            return $this->response(
                $dipulihkan
                    ? "Akun dengan email ini sebelumnya dihapus dan kini dipulihkan."
                    : "User registered.",
                Response::HTTP_CREATED, [
                'user'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->role,
                'isPetugasAcara' => $petugasAcara,
                'dipulihkan'     => $dipulihkan,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    function login(Request $request) {
        try {
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();

                // Identitas perangkat: satu sesi aktif per device (web, android, dst.)
                $deviceName = substr(trim((string) $request->input('device_name')), 0, 50) ?: 'web';

                // Cabut hanya token dari device yang sama — sesi device lain tetap hidup
                $user->tokens()
                    ->where('name', $deviceName)
                    ->where('revoked', false)
                    ->each(fn ($t) => $t->revoke());

                $token = $user->createToken($deviceName)->accessToken;

                AuditLog::create([
                    'action'      => 'login',
                    'resource'    => 'user',
                    'resource_id' => $user->email,
                    'performed_by'=> $user->email,
                    'role'        => $user->role,
                    'ip_address'  => $request->ip(),
                    'payload'     => null,
                ]);

                return $this->response("Access granted.", Response::HTTP_OK, [
                    'token' => $token,
                    'user'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                    // Karyawan bertanda Administrator Sekolah (staf TU) punya hak
                    // tulis operasional setara Admin — klien memakai ini untuk
                    // memutuskan menampilkan menu manajemen akademik.
                    'isAdminSekolah' => $user->isAdminSekolah(),
                    // Petugas Acara: akun yang HANYA boleh mengelola agenda.
                    // Ikut di sini karena klien menentukan menu dari hasil login,
                    // bukan dari panggilan tambahan — sama seperti penanda di atas.
                    'isPetugasAcara' => $user->isPetugasAcara(),
                    // true = akun masih memakai password default; client wajib
                    // mengarahkan ke layar ganti password sebelum fitur lain
                    'mustChangePassword' => (bool) ($user->must_change_password ?? false),
                ]);
            }

            return $this->response("Invalid user credentials.", Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password'     => ['required', 'different:current_password', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'confirm_password' => 'required|same:new_password',
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->all()
                );
            }

            $user = User::find(Auth::id());

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->response('Password saat ini tidak sesuai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $updates = ['password' => $request->new_password];

            // Ganti password memenuhi kewajiban akun berpassword default.
            // Guard truthy: aman sebelum migration (kolom belum ada -> null)
            if ($user->must_change_password ?? false) {
                $updates['must_change_password'] = false;
            }

            $user->update($updates);

            $this->auditLog('updated', 'user', $user->email, ['field' => 'password']);

            return $this->response('Password berhasil diubah.', Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $user = $request->user();

            // Cabut token yang sedang dipakai, terbitkan pengganti dengan device yang sama
            $deviceName = $request->user()->token()->name ?: 'web';
            $request->user()->token()->revoke();
            $token = $user->createToken($deviceName)->accessToken;

            $this->auditLog('refreshed', 'user', $user->email);

            return $this->response("Token refreshed.", Response::HTTP_OK, [
                'token' => $token,
                'user'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response("Logged out.", Response::HTTP_OK);
    }

    // Cabut SEMUA sesi aktif di semua device — dipakai jika akun dicurigai disalahgunakan
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());
        return $this->response("All sessions logged out.", Response::HTTP_OK);
    }
}
