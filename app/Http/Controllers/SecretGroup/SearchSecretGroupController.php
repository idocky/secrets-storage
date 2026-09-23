<?php

namespace App\Http\Controllers\SecretGroup;

use App\Http\Controllers\Controller;
use App\Models\SecretGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSecretGroupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $id = $request->integer('id') ?: null;

        $groups = SecretGroup::query()
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
                ->map(fn (SecretGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'tags' => $group->tagNames(),
                ])
                ->values(),
        ]);
    }
}
