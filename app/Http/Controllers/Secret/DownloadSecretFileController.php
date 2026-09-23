<?php

namespace App\Http\Controllers\Secret;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Models\SecretFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadSecretFileController extends Controller
{
    public function __invoke(Request $request, Secret $secret, SecretFile $secretFile): StreamedResponse
    {
        abort_unless($secretFile->secret_id === $secret->id, 404);
        abort_unless($secret->isVisibleTo($request->user()), 403);

        return Storage::disk(SecretFile::DISK)->download(
            $secretFile->storagePath(),
            $secretFile->original_name,
            $secretFile->mime_type ? ['Content-Type' => $secretFile->mime_type] : []
        );
    }
}
