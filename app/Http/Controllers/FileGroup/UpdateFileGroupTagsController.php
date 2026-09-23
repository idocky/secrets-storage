<?php

namespace App\Http\Controllers\FileGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFileGroupTagsRequest;
use App\Models\FileGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateFileGroupTagsController extends Controller
{
    public function __invoke(UpdateFileGroupTagsRequest $request, FileGroup $fileGroup): RedirectResponse
    {
        $fileGroup->syncTagsWithType($request->tags(), User::TAG_TYPE);

        return back();
    }
}
