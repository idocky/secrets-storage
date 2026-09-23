<?php

namespace App\Support;

use App\Models\User;
use Spatie\Tags\Tag;

class AccessTags
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return Tag::getWithType(User::TAG_TYPE)
            ->map(fn (Tag $tag) => (string) $tag->name)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $tags
     * @return list<string>
     */
    public static function normalize(mixed $tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }
}
