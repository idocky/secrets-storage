<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

#[Fillable(['secret_id', 'original_name', 'storage_name', 'mime_type', 'size'])]
class SecretFile extends Model
{
    public const DISK = 'spaces';

    public const DIRECTORY = 'secret-files';

    /**
     * @return BelongsTo<Secret, $this>
     */
    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class);
    }

    public function storagePath(): string
    {
        return self::DIRECTORY.'/'.$this->storage_name;
    }

    public function deleteFromStorage(): void
    {
        $path = $this->storagePath();

        // Do not gate on exists(): Spaces/S3 HeadObject can return 403 for private
        // objects, and with throw=false Laravel treats that as "missing".
        // DeleteObject is idempotent, so always attempt the delete.
        $deleted = Storage::disk(self::DISK)->delete($path);

        if ($deleted === false) {
            throw new RuntimeException("Не удалось удалить «{$path}» из Spaces");
        }
    }

    public static function storeForSecret(Secret $secret, UploadedFile $uploaded): self
    {
        $storageName = (string) Str::uuid();
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        if ($extension !== '') {
            $storageName .= '.'.$extension;
        }

        Storage::disk(self::DISK)->putFileAs(
            self::DIRECTORY,
            $uploaded,
            $storageName,
            ['visibility' => 'private'],
        );

        $originalName = $uploaded->getClientOriginalName();

        return $secret->files()->create([
            'original_name' => $originalName !== '' ? $originalName : $storageName,
            'storage_name' => $storageName,
            'mime_type' => $uploaded->getClientMimeType() ?: $uploaded->getMimeType(),
            'size' => $uploaded->getSize(),
        ]);
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
     * @return array{id: int, original_name: string, human_size: string, download_url: string}
     */
    public function toListPayload(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'human_size' => $this->human_size,
            'download_url' => route('secrets.files.download', [
                'secret' => $this->secret_id,
                'secretFile' => $this->id,
            ]),
        ];
    }
}
