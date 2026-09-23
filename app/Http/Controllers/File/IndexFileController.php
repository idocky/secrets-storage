<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileGroup;
use App\Models\User;
use App\Support\AccessTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexFileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $term = $q !== '' ? '%'.addcslashes($q, '%_\\').'%' : null;
        $like = File::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $groups = FileGroup::query()
            ->with('tags')
            ->visibleTo($user)
            ->withCount(['files as files_count' => fn (Builder $query) => $query->visibleTo($user)])
            ->when($term !== null, fn (Builder $query) => $query->where('name', $like, $term))
            ->orderBy('name')
            ->paginate(15, pageName: 'groups_page')
            ->withQueryString()
            ->through(fn (FileGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'tags' => $group->tagNames(),
                'files_count' => $group->files_count,
            ]);

        $files = File::query()
            ->with(['group', 'tags'])
            ->visibleTo($user)
            ->when($term === null, fn (Builder $query) => $query->whereNull('file_group_id'))
            ->when($term !== null, function (Builder $query) use ($like, $term) {
                $query->where(function (Builder $inner) use ($like, $term) {
                    $inner->where('original_name', $like, $term)
                        ->orWhereHas('group', fn (Builder $group) => $group->where('name', $like, $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (File $file) => $file->toListPayload());

        return Inertia::render('Files/Index', [
            'groups' => $groups,
            'files' => $files,
            'availableTags' => AccessTags::names(),
            'filters' => [
                'q' => $q,
            ],
        ]);
    }
}
