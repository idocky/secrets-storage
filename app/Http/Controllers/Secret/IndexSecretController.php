<?php

namespace App\Http\Controllers\Secret;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Models\SecretGroup;
use App\Models\User;
use App\Support\AccessTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexSecretController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $term = $q !== '' ? '%'.addcslashes($q, '%_\\').'%' : null;
        $like = Secret::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $groups = SecretGroup::query()
            ->with('tags')
            ->visibleTo($user)
            ->withCount(['secrets as secrets_count' => fn (Builder $query) => $query->visibleTo($user)])
            ->when($term !== null, fn (Builder $query) => $query->where('name', $like, $term))
            ->orderBy('name')
            ->paginate(15, pageName: 'groups_page')
            ->withQueryString()
            ->through(fn (SecretGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'tags' => $group->tagNames(),
                'secrets_count' => $group->secrets_count,
            ]);

        $secrets = Secret::query()
            ->with(['group', 'tags', 'files'])
            ->visibleTo($user)
            ->when($term === null, fn (Builder $query) => $query->whereNull('secret_group_id'))
            ->when($term !== null, function (Builder $query) use ($like, $term) {
                $query->where(function (Builder $inner) use ($like, $term) {
                    $inner->where('key', $like, $term)
                        ->orWhereHas('group', fn (Builder $group) => $group->where('name', $like, $term));
                });
            })
            ->orderBy('key')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Secret $secret) => $secret->toListPayload());

        return Inertia::render('Secrets/Index', [
            'groups' => $groups,
            'secrets' => $secrets,
            'availableTags' => AccessTags::names(),
            'filters' => [
                'q' => $q,
            ],
        ]);
    }
}
