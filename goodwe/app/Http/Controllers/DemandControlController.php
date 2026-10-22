<?php

namespace App\Http\Controllers;

use App\Models\Charger;
use App\Models\Establishment;
use App\Services\DemandControlService;
use App\Services\PowerDistributionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Painel de controle de demanda elétrica em tempo real do estabelecimento.
 *
 * Exibe o consumo total (kW) e a corrente ativa (A) agregados de todos os
 * carregadores em recarga (EM_ANDAMENTO), comparados com a capacidade máxima
 * configurada da infraestrutura elétrica local. Também oferece uma simulação
 * de distribuição equilibrada da potência entre os carregadores.
 */
class DemandControlController extends Controller
{
    public function __construct(
        private readonly DemandControlService $demand,
        private readonly PowerDistributionService $distribution,
    ) {}

    public function index(Request $request): View
    {
        $establishment = $request->user()->establishment;
        $status = $this->demand->status($establishment);
        $chargers = $this->chargers($establishment);
        $activeChargerIds = collect($status['active_sessions'])
            ->pluck('charger_id')
            ->filter()
            ->unique()
            ->all();

        $inputs = $chargers->mapWithKeys(fn (Charger $charger): array => [
            $charger->id => [
                // Sem sessões em andamento, simula a operação com todos os
                // carregadores cadastrados para facilitar a demonstração.
                'enabled' => $activeChargerIds === [] || in_array($charger->id, $activeChargerIds, true),
                'requested_kw' => (float) $charger->power_kw,
            ],
        ])->all();

        $oldInput = old('chargers');
        if (is_array($oldInput)) {
            $inputs = $this->mergeOldInputs($inputs, $oldInput);
        }

        return $this->panel($establishment, $status, $chargers, $inputs);
    }

    /**
     * Executa a simulação de distribuição sem persistir nada nem enviar
     * comandos aos carregadores. É uma rota de leitura: o cenário vem na
     * query string e pode ser compartilhado ou recarregado sem reenviar dados.
     */
    public function simulate(Request $request): View
    {
        $establishment = $request->user()->establishment;
        $chargers = $this->chargers($establishment);

        $validated = $request->validate([
            'chargers' => ['nullable', 'array'],
            'chargers.*.enabled' => ['nullable', 'boolean'],
            'chargers.*.requested_kw' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
        ]);

        $submitted = $validated['chargers'] ?? $request->old('chargers', []);
        if (! is_array($submitted)) {
            $submitted = [];
        }
        $inputs = $chargers->mapWithKeys(function (Charger $charger) use ($submitted): array {
            $row = is_array($submitted[$charger->id] ?? null) ? $submitted[$charger->id] : [];

            return [
                $charger->id => [
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'requested_kw' => (float) ($row['requested_kw'] ?? $charger->power_kw),
                ],
            ];
        })->all();

        return $this->panel($establishment, $this->demand->status($establishment), $chargers, $inputs);
    }

    /**
     * Dispara a sincronização das leituras a partir do Firestore (totens) e
     * redireciona para o painel com o resultado.
     */
    public function syncFromFirestore(Request $request, DemandControlService $demand): RedirectResponse
    {
        $establishment = $request->user()->establishment;

        try {
            $count = $demand->syncReadingsFromFirestore($establishment);

            return redirect()->route('manager.demand-control.index')
                ->with('success', "{$count} leitura(s) sincronizada(s) a partir dos totens.");
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('manager.demand-control.index')
                ->with('error', 'Não foi possível sincronizar as leituras dos totens.');
        }
    }

    /**
     * @param  array<string, array{enabled: bool, requested_kw: float}>  $inputs
     */
    private function panel(Establishment $establishment, array $status, Collection $chargers, array $inputs): View
    {
        $requests = [];
        $simulationChargers = [];

        foreach ($chargers as $charger) {
            $input = $inputs[$charger->id];
            $simulationChargers[] = [
                'id' => $charger->id,
                'name' => $charger->name,
                'code' => $charger->code,
                'power_kw' => (float) $charger->power_kw,
                'enabled' => (bool) $input['enabled'],
                'requested_kw' => (float) $input['requested_kw'],
            ];

            if ($input['enabled']) {
                $requests[] = [
                    'id' => $charger->id,
                    'name' => $charger->name,
                    'requested_kw' => (float) $input['requested_kw'],
                ];
            }
        }

        $simulation = $this->distribution->simulate(
            $establishment->max_power_kw !== null ? (float) $establishment->max_power_kw : null,
            $requests,
            (float) config('demand.minimum_allocation_kw', 1.4),
        );

        return view('manager.demand-control', compact('establishment', 'status', 'simulationChargers', 'simulation'));
    }

    private function chargers(Establishment $establishment): Collection
    {
        return $establishment->chargers()
            ->where('is_demo', false)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, array{enabled: bool, requested_kw: float}>  $inputs
     * @param  array<string, mixed>  $oldInput
     * @return array<string, array{enabled: bool, requested_kw: float}>
     */
    private function mergeOldInputs(array $inputs, array $oldInput): array
    {
        foreach ($oldInput as $id => $row) {
            if (! is_array($row) || ! array_key_exists($id, $inputs)) {
                continue;
            }

            $inputs[$id]['enabled'] = (bool) ($row['enabled'] ?? false);

            if (isset($row['requested_kw']) && is_numeric($row['requested_kw'])) {
                $inputs[$id]['requested_kw'] = (float) $row['requested_kw'];
            }
        }

        return $inputs;
    }
}
