<?php

namespace Database\Factories;

use App\Enums\SecretType;
use App\Models\Secret;
use App\Models\SecretGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const KEY_PREFIXES = [
        'API_KEY',
        'API_TOKEN',
        'DB_PASSWORD',
        'REDIS_PASSWORD',
        'JWT_SECRET',
        'APP_KEY',
        'SMTP_PASSWORD',
        'AWS_SECRET',
        'STRIPE_SECRET',
        'WEBHOOK_SECRET',
        'OAUTH_CLIENT_SECRET',
        'ENCRYPTION_KEY',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->randomElement(self::KEY_PREFIXES);

        return [
            'key' => strtoupper($prefix.'_'.Str::upper(Str::random(6))),
            'type' => SecretType::Password,
            'value' => fake()->password(16, 32),
            'description' => null,
            'environment' => fake()->randomElements(
                Secret::ENVIRONMENTS,
                fake()->numberBetween(1, count(Secret::ENVIRONMENTS)),
            ),
            'secret_group_id' => null,
        ];
    }

    public function forGroup(?SecretGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'secret_group_id' => $group?->id,
        ]);
    }

    public function forDev(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => ['dev'],
        ]);
    }

    public function forStaging(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => ['staging'],
        ]);
    }

    public function forProduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => ['production'],
        ]);
    }

    /**
     * @param  list<string>  $environments
     */
    public function forEnvironments(array $environments): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => array_values(array_unique(array_intersect(
                Secret::ENVIRONMENTS,
                $environments,
            ))),
        ]);
    }

    public function allEnvironments(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => Secret::ENVIRONMENTS,
        ]);
    }

    public function withoutValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => null,
        ]);
    }

    public function password(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SecretType::Password,
            'description' => null,
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SecretType::File,
            'value' => null,
        ]);
    }

    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SecretType::Note,
            'description' => null,
        ]);
    }
}
