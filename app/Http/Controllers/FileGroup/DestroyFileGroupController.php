<?php

namespace App\Http\Controllers\FileGroup;

use App\Http\Controllers\Controller;
use App\Models\FileGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyFileGroupController extends Controller
{
    public function __invoke(Request $request, FileGroup $fileGroup): RedirectResponse
    {
        abort_unless($fileGroup->isVisibleTo($request->user()), 403);

        $name = $fileGroup->name;
        $fileGroup->delete();

        return redirect()
            ->route('files.index')
            ->with('success', "Группа «{$name}» удалена");
    }
}
