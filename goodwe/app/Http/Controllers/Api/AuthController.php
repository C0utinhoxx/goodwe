<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'is_admin' => false,
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
        $credentials = [...$request->safe()->only(['email', 'password']), 'is_admin' => false];

        if (! Auth::guard('web')->validate($credentials)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $user = User::where('email', $credentials['email'])->where('is_admin', false)->firstOrFail();

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
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

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
            'points' => (float) $user->points,
            'is_admin' => (bool) $user->is_admin,
        ];
    }
}
