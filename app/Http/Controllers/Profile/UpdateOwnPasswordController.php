<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOwnPasswordRequest;
use Illuminate\Http\RedirectResponse;

class UpdateOwnPasswordController extends Controller
{
    public function __invoke(UpdateOwnPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password(),
        ]);

        return back()->with('success', 'Пароль обновлён');
    }
}
