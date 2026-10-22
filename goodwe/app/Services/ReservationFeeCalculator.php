<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Establishment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Calcula a taxa de reserva de um carregador.
 *
 * A base representa quanto custaria manter o carro carregando pelo tempo
 * médio que ele fica parado no local: tarifa vigente (R$/kWh) × duração
 * média das últimas recargas medidas (horas) × potência do carregador (kW).
 *
 * Sobre essa base aplica-se o acréscimo do dia: de segunda a quinta um
 * percentual menor, de sexta a domingo um percentual maior (configurado
 * em `config/reservations.php`).
 */
class ReservationFeeCalculator
{
    /**
     * @return array{enabled: bool, amount: string, base_amount: string, percent: int, average_minutes: int, is_weekend: bool}
     */
    public function quote(Charger $charger, ?CarbonInterface $day = null): array
    {
        $day ??= now();
        $isWeekend = $this->isWeekend($day);
        $percent = $isWeekend
            ? (int) config('reservations.fee_percent_weekend', 20)
            : (int) config('reservations.fee_percent_weekday', 10);

        $tariff = max(0.0, (float) $charger->currentTariff());
        $powerKw = max(0.0, (float) $charger->power_kw);
        $averageMinutes = $this->averageMinutes($charger->establishment);
        $baseUnits = (int) round($tariff * ($averageMinutes / 60) * $powerKw * 100);
        $amountUnits = (int) round($baseUnits * (100 + max(0, $percent)) / 100);

        return [
            'enabled' => (bool) config('reservations.fee_enabled') && $amountUnits > 0,
            'amount' => DecimalAmount::decimal($amountUnits),
            'base_amount' => DecimalAmount::decimal($baseUnits),
            'percent' => $percent,
            'average_minutes' => $averageMinutes,
            'is_weekend' => $isWeekend,
        ];
    }

    /**
     * Duração média (minutos) das últimas recargas medidas do estabelecimento.
     *
     * Sem histórico a média cai no padrão configurado. O resultado fica em
     * cache por dia: a lista de carregadores cita a taxa de todos os
     * equipamentos da página e cada consulta amostra até `fee_sample_size`
     * sessões.
     */
    public function averageMinutes(?Establishment $establishment): int
    {
        $default = max(1, (int) config('reservations.fee_default_average_minutes', 60));

        if ($establishment === null) {
            return $default;
        }

        $compute = function () use ($establishment, $default): int {
            $sampleSize = max(1, (int) config('reservations.fee_sample_size', 50));
            $sessions = ChargingSession::query()
                ->where('establishment_id', $establishment->id)
                ->where('is_demo', false)
                ->whereNotNull('started_at')
                ->whereNotNull('ended_at')
                ->latest('id')
                ->limit($sampleSize)
                ->get(['started_at', 'ended_at']);

            if ($sessions->isEmpty()) {
                return $default;
            }

            $totalSeconds = (int) $sessions->sum(fn (ChargingSession $session): int => abs($session->ended_at->diffInSeconds($session->started_at)));

            return max(1, (int) round($totalSeconds / 60 / $sessions->count()));
        };

        $cacheSeconds = (int) config('reservations.fee_average_cache_seconds', 300);

        if ($cacheSeconds <= 0) {
            return $compute();
        }

        return (int) Cache::remember(
            'reservation-fee:average-minutes:'.$establishment->id.':'.now()->toDateString(),
            $cacheSeconds,
            $compute
        );
    }

    private function isWeekend(CarbonInterface $day): bool
    {
        $weekendDays = array_map('intval', config('reservations.fee_weekend_days', [5, 6, 0]));

        return in_array((int) $day->dayOfWeek, $weekendDays, true);
    }
}
