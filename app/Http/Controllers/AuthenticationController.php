<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }
        $user = auth('sanctum')->user();
        if ($user->registered_at === null) {
            return response()->json([
                'message' => 'You have not confirmed your account yet.'
            ], 400);
        }
        return response()->json([
            'data' => [
                'token' => auth()->user()->createToken(time())->plainTextToken
            ]
        ]);
    }
}
