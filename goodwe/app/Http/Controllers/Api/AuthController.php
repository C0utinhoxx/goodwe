<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(UserRequest $request): JsonResponse
    {
        $request->validated();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        $token = $user->createToken('nfc-charger-app')->plainTextToken;

        return response()->json([
            'message' => __('auth.registered'),
            'token' => $token,
            'user' => $this->transformUser($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        $user->tokens()->where('name', 'nfc-charger-app')->delete();

        $token = $user->createToken('nfc-charger-app')->plainTextToken;

        return response()->json([
            'message' => __('auth.logged_in'),
            'token' => $token,
            'user' => $this->transformUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('auth.logged_out'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->transformUser($request->user()),
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'balance' => (float) $user->balance,
            'points' => (int) $user->points,
            'is_admin' => (bool) $user->is_admin,
        ];
    }
}
