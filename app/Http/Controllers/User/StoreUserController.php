<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreUserController extends Controller
{
    public function __invoke(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->login(),
            'email' => $request->login(),
            'password' => $request->password(),
            'is_admin' => $request->isAdmin(),
        ]);

        if ($request->tags() !== []) {
            $user->attachTags($request->tags(), User::TAG_TYPE);
        }

        $role = $request->isAdmin() ? 'администратор' : 'пользователь';

        return back()->with('success', "{$role} «{$request->login()}» создан");
    }
}
