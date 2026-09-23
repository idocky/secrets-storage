<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateUserPasswordController extends Controller
{
    public function __invoke(UpdateUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'password' => $request->password(),
        ]);

        return back()->with('success', "Пароль пользователя «{$user->email}» изменён");
    }
}
