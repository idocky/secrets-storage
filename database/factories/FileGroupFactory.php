<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FileGroup>
 */
class FileGroupFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const NAMES = [
        'Документы',
        'Договоры',
        'Инструкции',
        'Бэкапы',
        'Медиа',
        'Отчёты',
        'Лицензии',
        'Сертификаты',
        'Шаблоны',
        'Архив',
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

    public function withFiles(int $count = 3): static
    {
        return $this->has(
            File::factory()->count($count),
            'files',
        );
    }
}
