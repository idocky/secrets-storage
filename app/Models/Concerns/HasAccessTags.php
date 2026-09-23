<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\HasTags;

trait HasAccessTags
{
    use HasTags;

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return $this->tagsWithType(User::TAG_TYPE)
            ->map(fn ($tag) => (string) $tag->name)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $names = $user->tagNames();

        if ($names === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->withAnyTags($names, User::TAG_TYPE);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userTags = array_map(mb_strtolower(...), $user->tagNames());

        if ($userTags === []) {
            return false;
        }

        $ownTags = array_map(mb_strtolower(...), $this->tagNames());

        return array_intersect($userTags, $ownTags) !== [];
    }
}
