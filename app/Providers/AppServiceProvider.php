<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS sending (config/sms.php). A singleton, so tests can read what the fake "sent".
        $this->app->singleton(SmsSender::class, fn () => match ($driver = config('sms.driver')) {
            'log'   => new LogSmsSender(),
            'fake'  => new FakeSmsSender(),
            default => throw new InvalidArgumentException("Unknown SMS driver [{$driver}]."),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureApiRateLimits();
    }

    /**
     * Mobile API rate limits. Many members reach us from the same carrier IP
     * (MTN etc. use carrier-grade NAT), so per-IP limits are deliberately generous
     * and the real guards are per-user / per-phone.
     */
    private function configureApiRateLimits(): void
    {
        // Every /api route (attached to the 'api' group in bootstrap/app.php).
        RateLimiter::for('api', function (Request $request) {
            // The group runs before auth middleware, so resolve the token user explicitly.
            $user = $request->user('sanctum');

            return $user
                ? Limit::perMinute(60)->by('api-user:' . $user->getAuthIdentifier())
                : Limit::perMinute(120)->by('api-ip:' . $request->ip());
        });

        // Code requests and login (used from Step 3). Hourly SMS limits per phone come with Step 3.
        RateLimiter::for('api-auth', function (Request $request) {
            $phone = PhoneNumber::normalise((string) $request->input('phone'));

            return [
                // Same number in any spelling shares one bucket; unparseable input is bucketed per IP.
                Limit::perMinute(5)->by('api-auth-phone:' . ($phone ?? 'invalid:' . $request->ip())),
                Limit::perMinute(100)->by('api-auth-ip:' . $request->ip()),
            ];
        });
    }
}
