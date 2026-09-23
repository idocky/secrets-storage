<?php

namespace App\Http\Controllers\SecretGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSecretGroupTagsRequest;
use App\Models\SecretGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateSecretGroupTagsController extends Controller
{
    public function __invoke(UpdateSecretGroupTagsRequest $request, SecretGroup $secretGroup): RedirectResponse
    {
        $secretGroup->syncTagsWithType($request->tags(), User::TAG_TYPE);

        return back();
    }
}
