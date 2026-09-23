<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadFileController extends Controller
{
    public function __invoke(Request $request, File $file)
    {
        if ($file->is_public) {
            return $this->download($file);
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($file->isVisibleTo($request->user()), 403);

        return $this->download($file);
    }

    private function download(File $file)
    {
        return Storage::disk('spaces')->download(
            'tus-uploads/'.$file->storage_name,
            $file->original_name,
            $file->mime_type ? ['Content-Type' => $file->mime_type] : []
        );
    }
}
