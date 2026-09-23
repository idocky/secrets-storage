<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateUserController extends Controller
{
    public function __invoke(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'name' => $request->name(),
        ]);

        $user->syncTagsWithType($request->tags(), User::TAG_TYPE);

        return back()->with('success', "Пользователь «{$user->email}» обновлён");
    }
}
