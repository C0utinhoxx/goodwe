<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;

class ChargingSessionController extends Controller
{
    public function __construct(
        private readonly FirestoreService $firestore
    ) {}

    public function index(): JsonResponse
    {
        $sessions = $this->firestore->listCollection('charging_sessions');

        return response()->json($sessions);
    }
}
