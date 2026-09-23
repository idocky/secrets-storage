<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessTags;
use Inertia\Inertia;
use Inertia\Response;

class IndexUserController extends Controller
{
    public function __invoke(): Response
    {
        $users = User::query()
            ->with('tags')
            ->orderByDesc('is_admin')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'tags' => $user->tagNames(),
                'created_at' => $user->created_at
                    ?->timezone(config('app.timezone'))
                    ->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'availableTags' => AccessTags::names(),
        ]);
    }
}
