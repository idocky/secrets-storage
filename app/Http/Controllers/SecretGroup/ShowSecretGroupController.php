<?php

namespace App\Http\Controllers\SecretGroup;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Models\SecretGroup;
use App\Support\AccessTags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowSecretGroupController extends Controller
{
    public function __invoke(Request $request, SecretGroup $secretGroup): Response
    {
        abort_unless($secretGroup->isVisibleTo($request->user()), 403);

        $secrets = Secret::query()
            ->with(['group', 'tags', 'files'])
            ->where('secret_group_id', $secretGroup->id)
            ->visibleTo($request->user())
            ->orderBy('key')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Secret $secret) => $secret->toListPayload());

        $secretGroup->loadMissing('tags');

        return Inertia::render('Secrets/Group', [
            'group' => [
                'id' => $secretGroup->id,
                'name' => $secretGroup->name,
                'tags' => $secretGroup->tagNames(),
            ],
            'availableTags' => AccessTags::names(),
            'secrets' => $secrets,
        ]);
    }
}
