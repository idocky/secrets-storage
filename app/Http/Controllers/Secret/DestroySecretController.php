<?php

namespace App\Http\Controllers\Secret;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DestroySecretController extends Controller
{
    public function __invoke(Request $request, Secret $secret): RedirectResponse
    {
        abort_unless($secret->isVisibleTo($request->user()), 403);

        $key = $secret->key;

        try {
            $secret->delete();
        } catch (Throwable $e) {
            Log::error('Failed to delete secret files from Spaces', [
                'secret_id' => $secret->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Не удалось удалить файлы секрета «{$key}» из хранилища: {$e->getMessage()}");
        }

        return back()->with('success', "Секрет «{$key}» удалён");
    }
}
