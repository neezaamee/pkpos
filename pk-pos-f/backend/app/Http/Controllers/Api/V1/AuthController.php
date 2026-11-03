<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Handle registration of a new tenant and owner user.
     */
    public function register(RegisterRequest $request, RegisterTenantAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'message' => 'Registration successful.',
            'tenant' => $result['tenant'],
            'user' => $result['user'],
            'token' => $result['token'],
        ], 201);
    }

    /**
     * Authenticate user credentials and return a personal access token for POS registers.
     */
    public function login(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::withoutGlobalScopes()->where('email', $validated['email'])->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken('pos-register')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tenant' => $user->tenant,
        ]);
    }
}
