<?php

namespace App\Providers;

use App\Support\OrganizationMailConfig;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePasswordResetUrl();

        // §85 — outbound mail uses the organization's stored SMTP settings
        // when it supplies them, falling back to the server's env config.
        // Deferred to booted() so the DB and cache are ready and this never
        // runs before migrations on a fresh install.
        $this->app->booted(fn () => OrganizationMailConfig::apply());
    }

    /**
     * The reset-password page lives in the Next.js app, not Laravel — there
     * is no Fortify web view for it (this API has none), so the emailed
     * link must point at the frontend directly.
     */
    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (CanResetPassword $notifiable, string $token) {
            // Reuses config/cors.php's already-parsed allow-list rather than
            // reading FRONTEND_URLS again here — env() outside a config file
            // returns null once config is cached (Larastan: noEnvCallsOutsideOfConfig).
            /** @var list<string> $allowedOrigins */
            $allowedOrigins = config('cors.allowed_origins', []);
            $frontendUrl = rtrim($allowedOrigins[0] ?? 'http://localhost:3000', '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
