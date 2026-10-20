<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoHumano;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportDashboardController extends Controller
{
    /**
     * Status aceitos na URL e respectivo filtro aplicado às listagens
     * (null = visão geral, sem filtro).
     */
    private const STATUS_BY_PAGE = [
        'todos' => null,
        'aguardando' => 'aguardando',
        'em_atendimento' => 'em_atendimento',
        'resolvido' => 'resolvido',
    ];

    /**
     * Central de Atendimento — visão do perfil de suporte.
     *
     * Implementado como controller (e não closure) para permitir
     * `route:cache` em produção. As consultas vivem aqui: uma única agregação
     * substitui os quatro COUNTs separados que a view executava, além de
     * tirar o acesso ao banco de dentro do template.
     */
    public function __invoke(Request $request): View
    {
        $page = (string) $request->query('status', '');
        $status = self::STATUS_BY_PAGE[$page] ?? null;

        $tickets = AtendimentoHumano::query()
            ->leftJoin('users', 'users.id', '=', 'atendimento_humanos.user_id')
            ->select(
                'atendimento_humanos.*',
                'users.name as usuario_nome',
                'users.is_admin as solicitante_gestor'
            )
            ->when($status !== null, fn ($query) => $query->where('atendimento_humanos.status', $status))
            ->orderByDesc('atendimento_humanos.created_at')
            ->paginate(10)
            ->withQueryString();

        $totals = AtendimentoHumano::query()
            ->selectRaw('COUNT(*) as todos')
            ->selectRaw("COALESCE(SUM(status = 'aguardando'), 0) as aguardando")
            ->selectRaw("COALESCE(SUM(status = 'em_atendimento'), 0) as em_atendimento")
            ->selectRaw("COALESCE(SUM(status = 'resolvido'), 0) as resolvido")
            ->first();

        return view('suporte.dashboard', [
            'tickets' => $tickets,
            'totais' => [
                'todos' => (int) $totals->todos,
                'aguardando' => (int) $totals->aguardando,
                'em_atendimento' => (int) $totals->em_atendimento,
                'resolvido' => (int) $totals->resolvido,
            ],
        ]);
    }
}
