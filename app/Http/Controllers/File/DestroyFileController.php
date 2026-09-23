<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DestroyFileController extends Controller
{
    public function __invoke(Request $request, File $file): RedirectResponse
    {
        abort_unless($file->isVisibleTo($request->user()), 403);

        $path = 'tus-uploads/'.$file->storage_name;
        $name = $file->original_name;

        try {
            $disk = Storage::disk('spaces');

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            Log::error('Failed to delete file from Spaces', [
                'storage_name' => $file->storage_name,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Не удалось удалить «{$name}» из хранилища: {$e->getMessage()}");
        }

        $file->delete();

        return back()->with('success', "Файл «{$name}» удалён");
    }
}
