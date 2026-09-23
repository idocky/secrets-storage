<?php

namespace App\Http\Controllers\FileGroup;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileGroup;
use App\Support\AccessTags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowFileGroupController extends Controller
{
    public function __invoke(Request $request, FileGroup $fileGroup): Response
    {
        abort_unless($fileGroup->isVisibleTo($request->user()), 403);

        $files = File::query()
            ->with(['group', 'tags'])
            ->where('file_group_id', $fileGroup->id)
            ->visibleTo($request->user())
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (File $file) => $file->toListPayload());

        $fileGroup->loadMissing('tags');

        return Inertia::render('Files/Group', [
            'group' => [
                'id' => $fileGroup->id,
                'name' => $fileGroup->name,
                'tags' => $fileGroup->tagNames(),
            ],
            'availableTags' => AccessTags::names(),
            'files' => $files,
        ]);
    }
}
