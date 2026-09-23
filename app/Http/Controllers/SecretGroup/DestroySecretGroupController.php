<?php

namespace App\Http\Controllers\SecretGroup;

use App\Http\Controllers\Controller;
use App\Models\SecretGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroySecretGroupController extends Controller
{
    public function __invoke(Request $request, SecretGroup $secretGroup): RedirectResponse
    {
        abort_unless($secretGroup->isVisibleTo($request->user()), 403);

        $name = $secretGroup->name;
        $secretGroup->delete();

        return redirect()
            ->route('secrets.index')
            ->with('success', "Группа «{$name}» удалена");
    }
}
