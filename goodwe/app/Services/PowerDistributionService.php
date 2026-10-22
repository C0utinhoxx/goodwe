<?php

namespace App\Services;

/**
 * Calcula a distribuição equilibrada de potência entre carregadores.
 *
 * Enquanto a integração física não existe, o serviço é usado apenas para a
 * simulação exibida no portal do gestor: ele calcula e devolve a sugestão de
 * potência por carregador, sem enviar comandos aos equipamentos.
 */
class PowerDistributionService
{
    /**
     * Distribui a capacidade disponível entre os carregadores que solicitaram
     * recarga, em partes iguais e respeitando o limite individual de cada um.
     *
     * @param  array<int, array{id: int|string, name: string, requested_kw: float|int|string}>  $requests
     * @return array{
     *     capacity_kw: float|null,
     *     total_requested_kw: float,
     *     allocated_total_kw: float,
     *     unused_capacity_kw: float|null,
     *     deficit_kw: float,
     *     minimum_allocation_kw: float,
     *     balanced: bool,
     *     status: string,
     *     start_allowed: bool,
     *     message: string,
     *     allocations: array<int, array{
     *         id: int|string|null,
     *         name: string,
     *         requested_kw: float,
     *         allocated_kw: float,
     *         reduction_kw: float,
     *         limited_by_capacity: bool,
     *         below_minimum: bool,
     *         allocation_percent: float
     *     }>
     * }
     */
    public function simulate(?float $capacityKw, array $requests, ?float $minimumKw = null): array
    {
        $minimumKw = max(0.0, round($minimumKw ?? (float) config('demand.minimum_allocation_kw', 1.4), 3));
        $normalized = $this->normalizeRequests($requests);
        $totalRequestedKw = round((float) array_sum(array_column($normalized, 'requested_kw')), 3);

        if ($normalized === []) {
            return $this->result($capacityKw, 0.0, [], false, 'empty', true, $minimumKw);
        }

        if ($capacityKw === null) {
            $allocations = array_map(
                fn (array $request): array => $this->allocation($request, $request['requested_kw'], false),
                $normalized
            );

            return $this->result(null, $totalRequestedKw, $allocations, false, 'unlimited', true, $minimumKw);
        }

        $capacityKw = max(0.0, round($capacityKw, 3));
        $capacityMw = (int) round($capacityKw * 1000);

        if ($capacityMw === 0) {
            $allocations = array_map(
                fn (array $request): array => $this->allocation($request, 0.0, true, true),
                $normalized
            );

            return $this->result($capacityKw, $totalRequestedKw, $allocations, true, 'blocked', false, $minimumKw);
        }

        $requestedMw = array_map(
            fn (array $request): int => (int) round($request['requested_kw'] * 1000),
            $normalized
        );
        $allocatedMw = $this->waterFill($capacityMw, $requestedMw);

        $balanced = $totalRequestedKw > $capacityKw + 0.0005;
        $belowMinimum = false;
        $allocations = [];

        foreach ($normalized as $index => $request) {
            $allocatedKw = round($allocatedMw[$index] / 1000, 3);
            $limitedByCapacity = ($allocatedKw + 0.0005) < $request['requested_kw'];
            $below = $balanced && $limitedByCapacity && ($allocatedKw + 0.0005) < $minimumKw;
            $belowMinimum = $belowMinimum || $below;
            $allocations[] = $this->allocation(
                $request,
                $allocatedKw,
                $limitedByCapacity,
                $below
            );
        }

        $status = $balanced ? ($belowMinimum ? 'blocked' : 'balanced') : 'within_capacity';

        return $this->result($capacityKw, $totalRequestedKw, $allocations, $balanced, $status, ! $belowMinimum, $minimumKw);
    }

    /**
     * Reparte a capacidade em partes iguais entre os carregadores pendentes.
     * Quem pede menos que a cota é atendido integralmente e sai da divisão; o
     * valor restante continua sendo dividido entre os demais.
     *
     * O cálculo usa miliwatts inteiros para que a soma das cotas nunca
     * ultrapasse a capacidade por causa de arredondamento.
     *
     * @param  array<int, int>  $requestedMw
     * @return array<int, int>
     */
    private function waterFill(int $capacityMw, array $requestedMw): array
    {
        $allocations = array_fill(0, count($requestedMw), 0);
        $pending = $requestedMw;
        $remainingMw = $capacityMw;

        while ($pending !== [] && $remainingMw > 0) {
            $shareMw = intdiv($remainingMw, count($pending));
            $capped = array_filter($pending, fn (int $requested): bool => $requested <= $shareMw);

            if ($capped === []) {
                $remainderMw = $remainingMw - ($shareMw * count($pending));

                foreach ($pending as $index => $_) {
                    $allocations[$index] = $shareMw + ($remainderMw > 0 ? 1 : 0);
                    $remainderMw = max(0, $remainderMw - 1);
                }

                break;
            }

            foreach ($capped as $index => $requested) {
                $allocations[$index] = $requested;
                $remainingMw -= $requested;
                unset($pending[$index]);
            }
        }

        return $allocations;
    }

    /**
     * @param  array<int, array{id: int|string|null, name: string, requested_kw: float}>  $requests
     * @return array<int, array{id: int|string|null, name: string, requested_kw: float}>
     */
    private function normalizeRequests(array $requests): array
    {
        $normalized = [];

        foreach ($requests as $request) {
            $requestedKw = round((float) ($request['requested_kw'] ?? 0), 3);
            if ($requestedKw <= 0) {
                continue;
            }

            $normalized[] = [
                'id' => $request['id'] ?? null,
                'name' => (string) ($request['name'] ?? ''),
                'requested_kw' => $requestedKw,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{id: int|string|null, name: string, requested_kw: float}  $request
     * @return array{
     *     id: int|string|null,
     *     name: string,
     *     requested_kw: float,
     *     allocated_kw: float,
     *     reduction_kw: float,
     *     limited_by_capacity: bool,
     *     below_minimum: bool,
     *     allocation_percent: float
     * }
     */
    private function allocation(array $request, float $allocatedKw, bool $limitedByCapacity, bool $belowMinimum = false): array
    {
        $requestedKw = $request['requested_kw'];

        return [
            'id' => $request['id'],
            'name' => $request['name'],
            'requested_kw' => $requestedKw,
            'allocated_kw' => round($allocatedKw, 3),
            'reduction_kw' => round(max(0.0, $requestedKw - $allocatedKw), 3),
            'limited_by_capacity' => $limitedByCapacity,
            'below_minimum' => $belowMinimum,
            'allocation_percent' => $requestedKw > 0 ? round(($allocatedKw / $requestedKw) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array<int, array{id: int|string|null, name: string, requested_kw: float, allocated_kw: float, reduction_kw: float, limited_by_capacity: bool, below_minimum: bool, allocation_percent: float}>  $allocations
     * @return array<string, mixed>
     */
    private function result(
        ?float $capacityKw,
        float $totalRequestedKw,
        array $allocations,
        bool $balanced,
        string $status,
        bool $startAllowed,
        float $minimumKw
    ): array {
        $allocatedTotalKw = round((float) array_sum(array_column($allocations, 'allocated_kw')), 3);

        return [
            'capacity_kw' => $capacityKw,
            'total_requested_kw' => $totalRequestedKw,
            'allocated_total_kw' => $allocatedTotalKw,
            'unused_capacity_kw' => $capacityKw !== null ? max(0.0, round($capacityKw - $allocatedTotalKw, 3)) : null,
            'deficit_kw' => $capacityKw !== null ? max(0.0, round($totalRequestedKw - $capacityKw, 3)) : 0.0,
            'minimum_allocation_kw' => $minimumKw,
            'balanced' => $balanced,
            'status' => $status,
            'start_allowed' => $startAllowed,
            'message' => $this->message($status, $minimumKw),
            'allocations' => array_values($allocations),
        ];
    }

    private function message(string $status, float $minimumKw): string
    {
        return match ($status) {
            'unlimited' => 'Nenhum limite de potência está configurado. A simulação apenas repete a potência solicitada por cada carregador.',
            'within_capacity' => 'A demanda solicitada cabe integralmente na capacidade da instalação, sem necessidade de redistribuição.',
            'balanced' => 'A demanda total ultrapassa a capacidade. A potência foi redistribuída igualmente, respeitando o limite de cada carregador.',
            'blocked' => 'A capacidade é insuficiente mesmo após o balanceamento: ao menos um carregador ficaria abaixo da potência mínima de '.number_format($minimumKw, 2, ',', '.').' kW. Novas recargas devem ser bloqueadas.',
            'empty' => 'Selecione ao menos um carregador para simular a distribuição de potência.',
            default => '',
        };
    }
}
