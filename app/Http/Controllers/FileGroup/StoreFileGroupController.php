<?php

namespace App\Http\Controllers\FileGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileGroupRequest;
use App\Models\FileGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreFileGroupController extends Controller
{
    public function __invoke(StoreFileGroupRequest $request): RedirectResponse
    {
        $group = FileGroup::create([
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
