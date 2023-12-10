<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {   

        // Set access tokens to expire in 10 seconds JUST FOR DEBUG PURPOSES!
        //Passport::tokensExpireIn(now()->addSeconds(10));
        
        // Set access tokens to expire in 30 minutes
        Passport::tokensExpireIn(now()->addMinutes(30));

        // Set refresh tokens to expire in 30 days (adjust as needed)
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // Set personal access tokens to expire in 6 months (adjust as needed)
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
