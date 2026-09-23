<?php

namespace App\Providers;

use App\Secrets\SecretTypeRegistry;
use App\Secrets\Strategies\FileStrategy;
use App\Secrets\Strategies\NoteStrategy;
use App\Secrets\Strategies\PasswordStrategy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SecretTypeRegistry::class, function ($app) {
            return new SecretTypeRegistry([
                $app->make(PasswordStrategy::class),
                $app->make(FileStrategy::class),
                $app->make(NoteStrategy::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
