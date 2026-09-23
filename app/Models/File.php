<?php

namespace App\Models;

use App\Models\Concerns\HasAccessTags;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['original_name', 'storage_name', 'mime_type', 'size', 'checksum', 'is_public', 'file_group_id'])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasAccessTags, HasFactory;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FileGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(FileGroup::class, 'file_group_id');
    }

    public function getRouteKeyName(): string
    {
        return 'storage_name';
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = max(0, (int) $this->size);
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];

        if ($bytes === 0) {
            return '0 Б';
        }

        $i = (int) min(count($units) - 1, floor(log($bytes) / log(1024)));

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$units[$i];
    }

    /**
     * @return array{storage_name: string, original_name: string, mime_type: string|null, human_size: string, is_public: bool, group: array{id: int, name: string}|null, tags: list<string>, created_at: string|null, download_url: string}
     */
    public function toListPayload(): array
    {
        $group = $this->group;

        return [
            'storage_name' => $this->storage_name,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'human_size' => $this->human_size,
            'is_public' => (bool) $this->is_public,
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
            ] : null,
            'tags' => $this->tagNames(),
            'created_at' => $this->created_at
                ?->timezone(config('app.timezone'))
                ->format('d.m.Y H:i'),
            'download_url' => route('files.download', $this),
        ];
    }
}
