<?php

namespace App\Http\Controllers\Secret;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevealSecretController extends Controller
{
    public function __invoke(Request $request, Secret $secret): JsonResponse
    {
        abort_unless($secret->isVisibleTo($request->user()), 403);

        return response()->json([
            'value' => $secret->value,
        ]);
    }
}
