<?php

namespace Database\Seeders;

use App\Enums\SecretType;
use App\Models\Secret;
use App\Models\SecretGroup;
use Illuminate\Database\Seeder;

class SecretSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $backend = SecretGroup::query()->firstOrCreate(['name' => 'Backend']);
        $infra = SecretGroup::query()->firstOrCreate(['name' => 'Infrastructure']);
        $thirdParty = SecretGroup::query()->firstOrCreate(['name' => 'Third-party']);

        $presets = [
            [
                'key' => 'DB_PASSWORD',
                'value' => 'dev-db-password-change-me',
                'environment' => ['dev', 'staging'],
                'secret_group_id' => $infra->id,
            ],
            [
                'key' => 'API_TOKEN',
                'value' => 'api-token-'.bin2hex(random_bytes(12)),
                'environment' => ['dev', 'staging', 'production'],
                'secret_group_id' => $backend->id,
            ],
            [
                'key' => 'JWT_SECRET',
                'value' => bin2hex(random_bytes(32)),
                'environment' => ['production'],
                'secret_group_id' => $backend->id,
            ],
            [
                'key' => 'REDIS_PASSWORD',
                'value' => 'redis-local-secret',
                'environment' => ['dev'],
                'secret_group_id' => $infra->id,
            ],
            [
                'key' => 'SMTP_PASSWORD',
                'value' => 'smtp-staging-pass',
                'environment' => ['staging'],
                'secret_group_id' => $thirdParty->id,
            ],
        ];

        foreach ($presets as $preset) {
            Secret::query()->updateOrCreate(
                ['key' => $preset['key']],
                [
                    'type' => SecretType::Password,
                    'value' => $preset['value'],
                    'environment' => $preset['environment'],
                    'secret_group_id' => $preset['secret_group_id'],
                ],
            );
        }

        $groups = SecretGroup::query()->get();

        Secret::factory()
            ->count(20)
            ->state(fn () => [
                'secret_group_id' => $groups->isEmpty()
                    ? null
                    : $groups->random()->id,
            ])
            ->create();
    }
}
