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
                //Akademik service routes registration
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/akademik-service-routes.php'));
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
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Rate limiter 'throttle' dipakai oleh Passport di POST /oauth/token dan /oauth/device/code.
        // Tanpa definisi ini, Passport tidak membatasi request sama sekali → bypass brute force.
        RateLimiter::for('throttle', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'resCode'    => 429,
                        'resPhrase'  => 'Too Many Requests',
                        'resStatus'  => 'fail',
                        'resMsg'     => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.',
                        'data'       => [],
                    ], 429);
                });
        });
    }
}
