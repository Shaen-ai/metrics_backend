<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $base = rtrim(config('app.frontend_admin_url'), '/');

            return $base.'/reset-password?'.http_build_query([
                'email' => $user->getEmailForPasswordReset(),
                'token' => $token,
            ]);
        });
    }
}
