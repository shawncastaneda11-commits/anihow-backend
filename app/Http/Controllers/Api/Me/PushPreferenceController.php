<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->push_preferences,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['sometimes', 'boolean'],
            'payments' => ['sometimes', 'boolean'],
            'chats' => ['sometimes', 'boolean'],
            'farm_updates' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->push_preferences = array_merge($user->push_preferences, $validated);
        $user->save();

        return response()->json([
            'data' => $user->push_preferences,
        ]);
    }
}
