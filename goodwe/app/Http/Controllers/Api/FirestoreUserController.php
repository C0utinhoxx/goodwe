<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirestoreUserController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tag_uid' => 'required|string|max:255',
            'saldo_pontos' => 'required|numeric',
            'status_tag' => 'required|in:ativo,bloqueado',
            'comando_web' => 'required|in:espera,iniciar,parar',
        ]);

        $this->firestore->saveDocument('users', strtoupper($validated['tag_uid']), [
            'saldo_pontos' => $validated['saldo_pontos'],
            'status_tag' => $validated['status_tag'],
            'comando_web' => $validated['comando_web'],
        ]);

        return response()->json(['message' => 'Usuário salvo com sucesso.'], 201);
    }

    public function update(Request $request, string $tagUid): JsonResponse
    {
        $validated = $request->validate([
            'saldo_pontos' => 'sometimes|numeric',
            'status_tag' => 'sometimes|in:ativo,bloqueado',
            'comando_web' => 'sometimes|in:espera,iniciar,parar',
        ]);

        $this->firestore->updateDocument('users', strtoupper($tagUid), $validated);

        return response()->json(['message' => 'Usuário atualizado com sucesso.']);
    }

    public function setCommand(Request $request, string $tagUid): JsonResponse
    {
        $validated = $request->validate([
            'comando' => 'required|in:iniciar,parar',
        ]);

        $this->firestore->updateDocument('users', strtoupper($tagUid), [
            'comando_web' => $validated['comando'],
        ]);

        return response()->json(['message' => "Comando '{$validated['comando']}' enviado para a TAG {$tagUid}."]);
    }
}
