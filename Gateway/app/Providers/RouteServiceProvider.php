<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // /oauth/token didaftarkan manual karena Passport::ignoreRoutes() aktif.
            // 'throttle:5,1' = 5 request/menit per IP — sama dengan /api/login.
            Route::post('/oauth/token', [\Laravel\Passport\Http\Controllers\AccessTokenController::class, 'issueToken'])
                ->name('passport.token')
                ->middleware(['throttle:5,1']);

                //API Gateway specific routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));
                //Ruang Kelas microservice routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/class-service-routes.php'));
                //Mata Pelajaran service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/mapel-service-routes.php'));
                //Guru service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/guru-service-routes.php'));
                //Siswa service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/siswa-service-routes.php'));
                //Karyawan service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/karyawan-service-routes.php'));
                //Akademik service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/akademik-service-routes.php'));
                //Kartu absensi (utilitas QR) registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/kartu-routes.php'));
                //Absensi scan terminal registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/absensi-service-routes.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            // `$request->user()` memakai guard DEFAULT (`web`, berbasis sesi)
            // yang pada request API selalu null — kuncinya akan jatuh ke IP, dan
            // seluruh sekolah di balik satu NAT berbagi satu ember. Satu kelas
            // membuka app bersamaan sudah cukup untuk mengunci semuanya. Maka
            // guard `api` disebut eksplisit. Terverifikasi memang terpisah per
            // user: menembak akun A 22x menyisakan 216, akun B di IP yang sama
            // tetap 239.
            $user = $request->user('api');

            // Longgar dengan sengaja. Satu layar daftar berfoto = 1 permintaan
            // daftar + 25 permintaan foto (foto disajikan satu-per-request dari
            // disk private), jadi batas 60/menit akan memutus pemakaian normal.
            // Nilai ini tetap memotong pengerukan massal: menguras direktori
            // lewat `/foto/{id}` jadi berjam-jam, bukan sedetik.
            if ($user) {
                return Limit::perMinute(240)->by('u:' . $user->id);
            }

            // Cabang ini hanya tercapai di route TANPA `auth` (mis. /login, yang
            // sudah punya throttle:5,1 sendiri). Di route terproteksi ia tidak
            // pernah jalan: Laravel mengurutkan middleware lewat
            // $middlewarePriority, dan di sana AuthenticatesRequests berada DI
            // ATAS ThrottleRequests — `auth:api` menolak duluan, throttle tak
            // pernah dicapai. Terbukti lewat probe: 7 permintaan tanpa token ke
            // /api/user tidak memanggil limiter ini sama sekali. Jadi banjir
            // token palsu TIDAK terbatasi di sini; yang dihasilkan hanya 401
            // murah, dan tebak-kredensial tetap terkunci di /login.
            // Per-IP sengaja longgar: satu IP sekolah = ratusan perangkat.
            return Limit::perMinute(300)->by('ip:' . $request->ip());
        });
    }
}
