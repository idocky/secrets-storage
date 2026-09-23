<?php

namespace App\Providers;

use App\Secrets\SecretTypeRegistry;
use App\Secrets\Strategies\FileStrategy;
use App\Secrets\Strategies\NoteStrategy;
use App\Secrets\Strategies\PasswordStrategy;
use Aws\CommandInterface;
use Aws\Middleware;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
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
        Storage::extend('s3', function ($app, array $config) {
            $r2 = str_contains((string) ($config['endpoint'] ?? ''), 'r2.cloudflarestorage.com');

            if ($r2) {
                $config['request_checksum_calculation'] = 'when_required';
                $config['response_checksum_validation'] = 'when_required';
            }

            $disk = $app->make(FilesystemManager::class)->createS3Driver($config);

            if (! $r2) {
                return $disk;
            }

            $disk->getClient()->getHandlerList()->appendInit(
                Middleware::mapCommand(static function (CommandInterface $command) {
                    unset($command['ACL']);

                    return $command;
                }),
                'strip-r2-acl',
            );

            return $disk;
        });
    }
}
