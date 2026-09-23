<?php

namespace App\Http\Controllers\FileGroup;

use App\Http\Controllers\Controller;
use App\Models\FileGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchFileGroupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $id = $request->integer('id') ?: null;

        $groups = FileGroup::query()
            ->with('tags')
            ->visibleTo($request->user())
            ->when($q !== '', function ($query) use ($q) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where('name', $operator, '%'.addcslashes($q, '%_\\').'%');
            })
            ->when($q === '' && $id !== null, fn ($query) => $query->where('id', $id))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'groups' => $groups
                ->map(fn (FileGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'tags' => $group->tagNames(),
                ])
                ->values(),
        ]);
    }
}
