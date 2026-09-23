<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\FileGroup;
use App\Support\AccessTags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowUploadFileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $group = $request->filled('group')
            ? FileGroup::query()->with('tags')->find($request->integer('group'))
            : null;

        if ($group && ! $group->isVisibleTo($request->user())) {
            abort(403);
        }

        return Inertia::render('Files/Upload', [
            'tusEndpoint' => rtrim(url('/tus'), '/').'/',
            'downloadBase' => url('/download'),
            'availableTags' => AccessTags::names(),
            'initialGroup' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'tags' => $group->tagNames(),
            ] : null,
        ]);
    }
}
