<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = fake()->randomElement(['txt', 'pdf', 'bin', 'png']);

        return [
            'original_name' => fake()->unique()->word().'.'.$ext,
            'storage_name' => bin2hex(random_bytes(16)).'.'.$ext,
            'mime_type' => 'application/octet-stream',
            'size' => fake()->numberBetween(1, 10_000),
            'checksum' => null,
            'is_public' => false,
            'file_group_id' => null,
        ];
    }

    public function forGroup(?FileGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'file_group_id' => $group?->id,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
