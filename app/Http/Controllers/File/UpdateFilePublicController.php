<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFilePublicRequest;
use App\Models\File;
use Illuminate\Http\RedirectResponse;

class UpdateFilePublicController extends Controller
{
    public function __invoke(UpdateFilePublicRequest $request, File $file): RedirectResponse
    {
        $file->update([
            'is_public' => $request->isPublic(),
        ]);

        $status = $request->isPublic() ? 'публичным' : 'приватным';

        return back()->with('success', "Файл «{$file->original_name}» теперь {$status}");
    }
}
