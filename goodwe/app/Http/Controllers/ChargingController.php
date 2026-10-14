<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\ScheduleChargingRequest;
use App\Services\ChargingPaymentService;
use App\Services\ChargingSimulator;
use Illuminate\View\View;

class ChargingController extends Controller
{
    public function __construct(
        private ChargingPaymentService $payments,
    ) {}

    public function index(): View
    {
        return view('chargers.index', [
            'chargers' => ChargingSimulator::all(),
            'vehicles' => auth()->user()->vehicles()->latest()->get(),
            'coupons' => auth()->user()->coupons()->where('status', 'available')->latest()->get(),
        ]);
    }

    public function schedule(ScheduleChargingRequest $request, string $code): mixed
    {
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
        ChargingSimulator::release($code);

        return back()->with('success', __('chargers.released'));
    }
}
