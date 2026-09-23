<?php

namespace App\Http\Controllers\Secret;

use App\Enums\SecretType;
use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Models\SecretGroup;
use App\Secrets\SecretTypeRegistry;
use App\Support\AccessTags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateSecretController extends Controller
{
    public function __invoke(Request $request, SecretTypeRegistry $registry): Response
    {
        $group = $request->filled('group')
            ? SecretGroup::query()->with('tags')->find($request->integer('group'))
            : null;

        if ($group && ! $group->isVisibleTo($request->user())) {
            abort(403);
        }

        return Inertia::render('Secrets/Create', [
            'environments' => Secret::ENVIRONMENTS,
            'secretTypes' => $registry->contracts(),
            'defaultType' => SecretType::Password->value,
            'availableTags' => AccessTags::names(),
            'initialGroup' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'tags' => $group->tagNames(),
            ] : null,
        ]);
    }
}
