<?php

namespace App\Http\Controllers\Tus;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileGroup;
use App\Models\User;
use App\Support\AccessTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TusdHookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        return match ($payload['Type'] ?? null) {
            'pre-create' => $this->preCreate($request, $payload),
            'post-finish' => $this->postFinish($payload),
            default => response()->json(new \stdClass),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function preCreate(Request $request, array $payload): JsonResponse
    {
        if (! $request->user()) {
            return $this->reject(401, 'unauthenticated');
        }

        $upload = $payload['Event']['Upload'] ?? [];
        $size = $upload['Size'] ?? null;

        if (! is_numeric($size) || (int) $size < 0) {
            return $this->reject(400, 'invalid upload size');
        }

        $max = (int) config('tus.max_upload_size');
        if ((int) $size > $max) {
            return $this->reject(413, 'file too large');
        }

        $storageName = $this->storageNameFromUpload($upload);
        if ($storageName === null) {
            return $this->reject(400, 'invalid filename');
        }

        $metadata = $this->metadata($upload);
        $groupId = $this->groupIdFromMetadata($metadata);

        if ($groupId !== null) {
            $group = FileGroup::query()->find($groupId);

            if ($group === null || ! $group->isVisibleTo($request->user())) {
                return $this->reject(400, 'group not found');
            }
        }

        $tags = $this->tagsFromMetadata($metadata);
        foreach ($tags as $tag) {
            if (mb_strlen($tag) > 50) {
                return $this->reject(400, 'invalid tags');
            }
        }

        return response()->json([
            'ChangeFileInfo' => [
                'ID' => $storageName,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postFinish(array $payload): JsonResponse
    {
        $upload = $payload['Event']['Upload'] ?? [];
        $metadata = $this->metadata($upload);
        $storageName = $this->storageNameFromUpload($upload);

        if ($storageName === null) {
            $key = $upload['Storage']['Key'] ?? '';
            $storageName = is_string($key) ? basename($key) : '';
        }

        if ($storageName === '' || ! $this->isValidStorageName($storageName)) {
            return response()->json(new \stdClass);
        }

        $file = File::query()->firstOrCreate(
            ['storage_name' => $storageName],
            [
                'original_name' => $metadata['originalname'] ?? $storageName,
                'mime_type' => $metadata['filetype'] ?? null,
                'size' => (int) ($upload['Size'] ?? 0),
                'checksum' => null,
                'file_group_id' => $this->existingGroupId($this->groupIdFromMetadata($metadata)),
            ],
        );

        if ($file->wasRecentlyCreated) {
            $tags = $this->tagsFromMetadata($metadata);

            if ($tags !== []) {
                $file->attachTags($tags, User::TAG_TYPE);
            }
        }

        return response()->json(new \stdClass);
    }

    /**
     * @param  array<string, mixed>  $upload
     * @return array<string, string>
     */
    private function metadata(array $upload): array
    {
        $metadata = $upload['MetaData'] ?? [];
        if (! is_array($metadata)) {
            return [];
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $normalized[strtolower($key)] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function groupIdFromMetadata(array $metadata): ?int
    {
        $raw = $metadata['groupid'] ?? '';

        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function existingGroupId(?int $groupId): ?int
    {
        if ($groupId === null) {
            return null;
        }

        return FileGroup::query()->whereKey($groupId)->exists() ? $groupId : null;
    }

    /**
     * @param  array<string, string>  $metadata
     * @return list<string>
     */
    private function tagsFromMetadata(array $metadata): array
    {
        $raw = trim($metadata['tags'] ?? '');

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $decoded = explode(',', $raw);
        }

        return AccessTags::normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $upload
     */
    private function storageNameFromUpload(array $upload): ?string
    {
        $filename = $this->metadata($upload)['filename'] ?? null;
        if (! is_string($filename) || ! $this->isValidStorageName($filename)) {
            return null;
        }

        return $filename;
    }

    private function isValidStorageName(string $name): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}(?:\.[A-Za-z0-9]+)?$/', $name);
    }

    private function reject(int $status, string $message): JsonResponse
    {
        return response()->json([
            'RejectUpload' => true,
            'HTTPResponse' => [
                'StatusCode' => $status,
                'Body' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
                'Header' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ]);
    }
}
