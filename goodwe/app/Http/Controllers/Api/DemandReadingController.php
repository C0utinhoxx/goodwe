<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemandReadingRequest;
use App\Services\DemandControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Endpoint para o adaptador IoT/Firestore reportar a demanda elétrica ativa
 * (kW e A) dos carregadores, e para consultar o agregado atual.
 */
class DemandReadingController extends Controller
{
    public function __construct(private readonly DemandControlService $demand) {}

    public function store(StoreDemandReadingRequest $request): JsonResponse
    {
        $establishment = $request->user()->establishment;
        $validated = $request->validated();

        $charger = $establishment->chargers()
            ->where('code', $validated['charger_code'])
            ->first();

        if (! $charger) {
            return response()->json(['message' => 'Carregador não encontrado neste estabelecimento.'], 404);
        }

        $reading = $this->demand->recordReading($establishment, $charger, [
            'external_id' => $validated['external_id'],
            'active_power_kw' => $validated['active_power_kw'] ?? 0,
            'current_a' => $validated['current_a'] ?? null,
            'voltage_v' => $validated['voltage_v'] ?? null,
            'recorded_at' => isset($validated['recorded_at']) ? Carbon::parse($validated['recorded_at']) : now(),
            'source' => $validated['source'] ?? 'iot',
        ]);

        return response()->json([
            'reading' => [
                'id' => $reading->id,
                'charger_id' => $reading->charger_id,
                'active_power_kw' => (float) $reading->active_power_kw,
                'current_a' => (float) $reading->current_a,
                'recorded_at' => $reading->recorded_at?->toIso8601String(),
            ],
            'summary' => $this->demand->status($establishment),
        ], 201);
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->demand->status(request()->user()->establishment));
    }
}
