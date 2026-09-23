<?php

namespace App\Http\Controllers\SecretGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecretGroupRequest;
use App\Models\SecretGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreSecretGroupController extends Controller
{
    public function __invoke(StoreSecretGroupRequest $request): RedirectResponse
    {
        $group = SecretGroup::create([
            'name' => $request->name(),
        ]);

        if ($request->tags() !== []) {
            $group->attachTags($request->tags(), User::TAG_TYPE);
        }

        $group->load('tags');

        return back()
            ->with('success', "Группа «{$group->name}» создана")
            ->with('created_group', [
                'id' => $group->id,
                'name' => $group->name,
                'tags' => $group->tagNames(),
            ]);
    }
}
