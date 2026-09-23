<?php

namespace Database\Factories;

use App\Models\Secret;
use App\Models\SecretGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SecretGroup>
 */
class SecretGroupFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const NAMES = [
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = fake()->randomElement(self::NAMES);

        return [
            'name' => $base.' '.Str::upper(Str::random(5)),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    public function withSecrets(int $count = 3): static
    {
        return $this->has(
            Secret::factory()->count($count),
            'secrets',
        );
    }
}
