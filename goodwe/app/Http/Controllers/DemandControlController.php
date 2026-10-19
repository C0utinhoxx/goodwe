<?php

namespace App\Http\Controllers;

use App\Services\DemandControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Painel de controle de demanda elétrica em tempo real do estabelecimento.
 *
 * Exibe o consumo total (kW) e a corrente ativa (A) agregados de todos os
 * carregadores em recarga (EM_ANDAMENTO), comparados com a capacidade máxima
 * configurada da infraestrutura elétrica local.
 */
class DemandControlController extends Controller
{
    public function __construct(private readonly DemandControlService $demand) {}

    public function index(Request $request): View
    {
        $establishment = $request->user()->establishment;

        return view('manager.demand-control', [
            'establishment' => $establishment,
            'status' => $this->demand->status($establishment),
        ]);
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
}
