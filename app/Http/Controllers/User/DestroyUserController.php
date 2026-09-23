<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyUserController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Нельзя удалить свой аккаунт');
        }

        $email = $user->email;
        $user->delete();

        return back()->with('success', "Пользователь «{$email}» удалён");
    }
}
