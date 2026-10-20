<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint para o app consultar as sessões de recarga espelhadas no Firestore.
 */
class ChargingSessionController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Janela fixa por vez: sem limite a chamada varre a coleção inteira do
        // Firestore a cada requisição do app.
        $perPage = min(max($request->integer('per_page', 100), 1), 500);

        return response()->json($this->firestore->listCollection('charging_sessions', $perPage));
    }
}
