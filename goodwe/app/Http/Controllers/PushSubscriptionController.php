<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user && $user->isGestor(),
            403,
            'Apenas gestores podem ativar notificações.'
        );

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:4096'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'contentEncoding' => [
                'nullable',
                Rule::in(['aes128gcm', 'aesgcm']),
            ],
        ]);

        $endpoint = $validated['endpoint'];

        $parts = parse_url($endpoint);

        abort_unless(
            is_array($parts)
                && ($parts['scheme'] ?? null) === 'https'
                && ! empty($parts['host'])
                && empty($parts['user'])
                && empty($parts['pass'])
                && ! isset($parts['fragment']),
            422,
            'Endpoint de notificação inválido.'
        );

        $hash = hash('sha256', $endpoint);

        $existing = PushSubscription::query()
            ->where('endpoint_hash', $hash)
            ->first();

        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este dispositivo já está associado a outra conta.',
            ], 409);
        }

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificações ativadas neste dispositivo.',
            'subscription_id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user && $user->isGestor(),
            403,
            'Apenas gestores podem desativar notificações.'
        );

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:4096'],
        ]);

        $hash = hash('sha256', $validated['endpoint']);

        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint_hash', $hash)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscrição de notificações removida.',
        ]);
    }
}
