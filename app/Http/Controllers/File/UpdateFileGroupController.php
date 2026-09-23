<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFileGroupRequest;
use App\Models\File;
use Illuminate\Http\RedirectResponse;

class UpdateFileGroupController extends Controller
{
    public function __invoke(UpdateFileGroupRequest $request, File $file): RedirectResponse
    {
        $group = $request->group();

        abort_unless($group->isVisibleTo($request->user()), 403);

        if ((int) $file->file_group_id === (int) $group->id) {
            return back();
        }

        $file->update([
            'file_group_id' => $group->id,
        ]);

        return back()->with('success', "Файл «{$file->original_name}» перенесён в группу «{$group->name}»");
    }
}
