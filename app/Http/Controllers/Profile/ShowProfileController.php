<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->load('tags');

        return Inertia::render('Profile/Show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'tags' => $user->tagNames(),
            ],
        ]);
    }
}
