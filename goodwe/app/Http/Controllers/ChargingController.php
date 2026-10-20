<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\ScheduleChargingRequest;
use App\Models\Charger;
use App\Services\ChargingPaymentService;
use App\Services\ChargingSimulator;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingController extends Controller
{
    /** Tamanho da página da lista pública de carregadores. */
    private const PER_PAGE = 24;

    /** Status aceitos no filtro da listagem pública ('all' = sem filtro). */
    private const STATUSES = ['all', 'available', 'reserved', 'in_use', 'unavailable'];

    public function __construct(
        private ChargingPaymentService $payments,
    ) {}

    public function index(Request $request, ReservationService $reservations): View
    {
        $reservations->expireDueOnRequests();
        $user = auth()->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'power' => is_numeric($request->query('power')) ? (float) $request->query('power') : null,
        ];

        if (! in_array($filters['status'], self::STATUSES, true)) {
            $filters['status'] = 'all';
        }

        // Janela paginada com busca e filtros no servidor: antes a página
        // carregava todos os carregadores (cartão + diálogo) e filtrava no
        // cliente, custando O(total) a cada visita.
        $chargers = Charger::query()
            ->where('is_demo', false)
            ->with(['establishment', 'activeReservation'])
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$filters['q']}%")
                    ->orWhere('location', 'like', "%{$filters['q']}%")
                    ->orWhere('connector', 'like', "%{$filters['q']}%")
                    ->orWhereHas('establishment', fn (Builder $establishment) => $establishment->where('name', 'like', "%{$filters['q']}%"))
            ))
            ->when($filters['power'] !== null, fn (Builder $query) => $query->where('power_kw', $filters['power']))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->whereRaw(Charger::STATUS_SQL.' = ?', [$filters['status']]))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('chargers.index', [
            'chargers' => $chargers,
            'filters' => $filters,
            // As opções do filtro de potência vêm de uma consulta distinta, não
            // da página carregada.
            'powerOptions' => Charger::where('is_demo', false)->distinct()->orderBy('power_kw')->pluck('power_kw'),
            'reservations' => $user->reservations()->where('is_demo', false)->with(['charger', 'establishment'])->latest()->paginate(10, ['*'], 'reservations_page'),
            'activeSessions' => $user->chargingSessions()->where('is_demo', false)->with('establishment')->whereNotNull('charging_state')->where('payment_status', '!=', 'paid')->latest()->get(),
        ]);
    }

    public function schedule(ScheduleChargingRequest $request, string $code): mixed
    {
        if (Charger::where('code', $code)->exists()) {
            abort(409, 'Carregadores cadastrados utilizam reserva imediata; o início é confirmado pelo equipamento.');
        }
        try {
            $session = $this->payments->schedule($request->user(), $code, $request->validated());
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('chargers.index')
            ->with('success', 'Pagamento confirmado: R$ '.number_format($session->amount_charged, 2, ',', '.').($session->coupon_id ? ' com desconto do cupom.' : '.').' Recarga agendada no simulador.');
    }

    public function release(string $code): mixed
    {
        if (Charger::where('code', $code)->exists()) {
            abort(409, 'O estado físico só pode ser atualizado pela integração do equipamento.');
        }
        ChargingSimulator::release($code);

        return back()->with('success', __('chargers.released'));
    }
}
