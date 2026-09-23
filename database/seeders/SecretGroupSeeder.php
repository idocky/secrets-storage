<?php

namespace Database\Seeders;

use App\Models\SecretGroup;
use Illuminate\Database\Seeder;

class SecretGroupSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PRESETS = [
        'Backend',
        'Frontend',
        'Infrastructure',
        'Mobile',
        'Analytics',
        'CI/CD',
        'Third-party',
        'Billing',
        'Auth',
        'Notifications',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PRESETS as $name) {
            SecretGroup::query()->firstOrCreate(['name' => $name]);
        }

        $existing = SecretGroup::query()->count();
        $target = 20;

        if ($existing < $target) {
            SecretGroup::factory()
                ->count($target - $existing)
                ->create();
        }
    }
}
