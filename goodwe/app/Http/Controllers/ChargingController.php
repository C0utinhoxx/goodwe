<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\ScheduleChargingRequest;
use App\Models\Charger;
use App\Services\ChargingPaymentService;
use App\Services\ChargingSimulator;
use App\Services\ReservationService;
use Illuminate\View\View;

class ChargingController extends Controller
{
    public function __construct(
        private ChargingPaymentService $payments,
    ) {}

    public function index(ReservationService $reservations): View
    {
        $reservations->expireDue();
        $user = auth()->user();

        return view('chargers.index', [
            'chargers' => Charger::where('is_demo', false)->with(['establishment', 'activeReservation'])->orderBy('name')->get(),
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
