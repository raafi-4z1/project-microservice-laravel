<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\CarbonInterval;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Access token: 8 jam (satu hari kerja)
        Passport::tokensExpireIn(CarbonInterval::hours(8));

        // Personal access token (dipakai createToken()): 8 jam
        Passport::personalAccessTokensExpireIn(CarbonInterval::hours(8));

        // Refresh token: 30 hari — user re-login setelah sebulan idle
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    }
}

