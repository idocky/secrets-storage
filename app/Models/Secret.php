<?php

namespace App\Models;

use App\Enums\SecretType;
use App\Models\Concerns\HasAccessTags;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'type', 'value', 'description', 'environment', 'secret_group_id'])]
#[Hidden(['value'])]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasAccessTags, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'password',
    ];

    /**
     * @return BelongsTo<SecretGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SecretGroup::class, 'secret_group_id');
    }

    /**
     * @return HasMany<SecretFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(SecretFile::class)->orderBy('id');
    }

    public const ENVIRONMENTS = ['dev', 'staging', 'production'];

    protected static function booted(): void
    {
        static::deleting(function (Secret $secret): void {
            $secret->deleteStoredFiles();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => SecretType::class,
            'value' => 'encrypted',
            'environment' => 'array',
        ];
    }

    /**
     * @return array{dev: bool, staging: bool, production: bool}
     */
    public function environmentFlags(): array
    {
        $selected = array_values(array_intersect(self::ENVIRONMENTS, $this->environment ?? []));

        return [
            'dev' => in_array('dev', $selected, true),
            'staging' => in_array('staging', $selected, true),
            'production' => in_array('production', $selected, true),
        ];
    }

    /**
     * @return array{id: int, key: string, type: string, type_label: string, group: array{id: int, name: string}|null, tags: list<string>, environment: array{dev: bool, staging: bool, production: bool}, has_value: bool, description: string|null, files: list<array{id: int, original_name: string, human_size: string, download_url: string}>, created_at: string|null}
     */
    public function toListPayload(): array
    {
        $group = $this->group;
        $this->loadMissing('files');
        $type = $this->type ?? SecretType::Password;

        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $type->value,
            'type_label' => $type->label(),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
            ] : null,
            'tags' => $this->tagNames(),
            'environment' => $this->environmentFlags(),
            'has_value' => filled($this->getAttributes()['value'] ?? null),
            'description' => $this->description,
            'files' => $this->files
                ->map(fn (SecretFile $file) => $file->toListPayload())
                ->values()
                ->all(),
            'created_at' => $this->created_at
                ?->timezone(config('app.timezone'))
                ->format('d.m.Y H:i'),
        ];
    }

    public function deleteStoredFiles(): void
    {
        $this->loadMissing('files');

        foreach ($this->files as $file) {
            $file->deleteFromStorage();
        }
    }
}
