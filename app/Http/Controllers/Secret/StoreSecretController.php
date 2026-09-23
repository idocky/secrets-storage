<?php

namespace App\Http\Controllers\Secret;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;
use App\Models\User;
use App\Secrets\SecretTypeRegistry;
use Illuminate\Http\RedirectResponse;

class StoreSecretController extends Controller
{
    public function __invoke(StoreSecretRequest $request, SecretTypeRegistry $registry): RedirectResponse
    {
        $groupId = $request->secretGroupId();
        $strategy = $registry->get($request->secretType());

        $secret = Secret::create([
            'key' => $request->key(),
            'type' => $strategy->type(),
            'environment' => $request->environments(),
            'secret_group_id' => $groupId,
        ]);

        $strategy->persist($secret, $request);

        if ($request->tags() !== []) {
            $secret->attachTags($request->tags(), User::TAG_TYPE);
        }

        $redirect = $groupId
            ? redirect()->route('secrets.group', $groupId)
            : redirect()->route('secrets.index');

        return $redirect->with('success', "Секрет «{$request->key()}» создан");
    }
}
