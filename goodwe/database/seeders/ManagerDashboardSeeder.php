<?php

namespace Database\Seeders;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ManagerDashboardSeeder extends Seeder
{
    use WithoutModelEvents;

    public const MANAGER_EMAIL = 'gestor.powertag@test.com';

    public const MANAGER_PASSWORD = '123456';

    /** Quantidade de dias de histórico usada para alimentar os filtros de 7/30/90 dias. */
    private const HISTORY_DAYS = 90;

    /**
     * Abaixo deste total, o histórico é considerado incompleto (por exemplo,
     * um boot interrompido no meio da carga) e o seeder roda novamente.
     */
    private const MIN_COMPLETE_SESSIONS = 400;

    public function run(): void
    {
        $manager = $this->manager();
        $establishment = $this->establishment($manager);

        // Em produção o entrypoint chama este seeder a cada boot. Depois da
        // primeira carga, preservamos as sessões existentes (inclusive as
        // recargas reais feitas pelos totens) e evitamos reescrever o painel.
        // Uma carga parcial (boot interrompido) é refeita.
        if (app()->environment('production') && ChargingSession::query()
            ->where('establishment_id', $establishment->id)
            ->where('is_demo', true)
            ->count() >= self::MIN_COMPLETE_SESSIONS) {
            $this->command->info('Histórico de demonstração completo já existe; seeder ignorado.');

            return;
        }

        $chargers = $this->chargers($establishment);
        $drivers = $this->drivers();

        $sessions = $this->sessions($establishment, $chargers, $drivers);

        $this->forgetDashboardCache($establishment);

        $this->command->info('Dados de demonstração do gestor criados com sucesso!');
        $this->command->info('Email: '.self::MANAGER_EMAIL);
        $this->command->info('Senha: '.self::MANAGER_PASSWORD);
        $this->command->info("Estabelecimento: {$establishment->name}");
        $this->command->info('Carregadores: '.$chargers->pluck('code')->implode(', '));
        $this->command->info("Sessões fake: {$sessions} nos últimos ".self::HISTORY_DAYS.' dias');
    }

    private function manager(): User
    {
        $manager = User::updateOrCreate(
            ['email' => self::MANAGER_EMAIL],
            [
                'name' => 'Gestor PowerTag',
                'password' => Hash::make(self::MANAGER_PASSWORD),
                'is_admin' => true,
            ]
        );

        return $manager;
    }

    private function establishment(User $manager): Establishment
    {
        return Establishment::updateOrCreate(
            ['user_id' => $manager->id],
            [
                'name' => 'PowerTag Shopping Center',
                'address' => 'Av. Cruzeiro do Sul, 1800 - Santana, São Paulo - SP',
                'type' => Establishment::TYPE_SHOPPING,
                'operating_hours' => '10:00 - 22:00',
                'phone' => '(11) 3456-7890',
                'cost_per_kwh' => 1.8500,
                'reservation_tolerance_minutes' => 15,
                'no_show_penalty_points' => 10.00,
            ]
        );
    }

    /**
     * Vincula os totens configurados na tela de recarga ao estabelecimento do
     * gestor de demonstração. O `code` é o mesmo `id` do documento na coleção
     * `totems` do Firestore, usado pela TotemSessionService para atribuir a
     * recarga ao painel correto.
     *
     * @return Collection<int, Charger>
     */
    private function chargers(Establishment $establishment): Collection
    {
        $configured = collect(config('totems.available', []))->keyBy('id');

        return collect(['totem_01', 'totem_02'])->map(function (string $code, int $index) use ($configured, $establishment): Charger {
            $totem = $configured->get($code, []);

            $charger = Charger::updateOrCreate(
                ['code' => $code],
                [
                    'establishment_id' => $establishment->id,
                    'name' => $totem['name'] ?? 'Totem '.($index + 1),
                    'location' => $totem['location'] ?? 'Área de recarga',
                    'power_kw' => $index === 0 ? 22 : 11,
                    'connector' => 'Type 2',
                    'activation_status' => 'active',
                    'physical_status' => 'available',
                    'physical_status_updated_at' => now(),
                ]
            );

            // Os totens são equipamentos reais de teste; não podem entrar no
            // fluxo de demonstração isolada (que exige `is_demo`).
            $charger->forceFill(['is_demo' => false])->saveQuietly();

            return $charger;
        })->values();
    }

    /**
     * Motoristas fictícios usados nas sessões do painel.
     *
     * @return Collection<int, User>
     */
    private function drivers(): Collection
    {
        $names = [
            'Ana Beatriz Souza',
            'Carlos Eduardo Lima',
            'Fernanda Oliveira',
            'João Pedro Martins',
            'Larissa Almeida',
            'Marcos Vinícius Rocha',
            'Patrícia Gomes',
            'Rafael Costa',
        ];

        return collect($names)->map(fn (string $name, int $index): User => User::updateOrCreate(
            ['email' => 'motorista'.($index + 1).'@powertag.test'],
            [
                'name' => $name,
                'password' => Hash::make(self::MANAGER_PASSWORD),
                'balance' => 250 + ($index * 35),
                'points' => 500 + ($index * 180),
            ]
        ))->values();
    }

    /**
     * Gera um histórico de recargas distribuído ao longo dos últimos 90 dias,
     * sempre com sessões concluídas para todos os dias (gráficos preenchidos).
     *
     * As linhas são marcadas com `is_demo = true` para que uma nova execução
     * do seeder substitua apenas os dados fake, preservando recargas reais que
     * o gestor venha a fazer na tela de recarga.
     *
     * @param  Collection<int, Charger>  $chargers
     * @param  Collection<int, User>  $drivers
     */
    private function sessions(Establishment $establishment, Collection $chargers, Collection $drivers): int
    {
        ChargingSession::query()
            ->where('establishment_id', $establishment->id)
            ->where('is_demo', true)
            ->delete();

        // Gerador determinístico nativo: em produção o Composer roda com
        // --no-dev e a Faker (require-dev) não está disponível na imagem.
        mt_srand(20261022);

        $tariff = (float) $establishment->cost_per_kwh;
        $start = now()->subDays(self::HISTORY_DAYS - 1)->startOfDay();
        $rows = [];

        for ($day = 0; $day < self::HISTORY_DAYS; $day++) {
            $date = $start->copy()->addDays($day);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->isToday() ? now()->subMinutes(90) : $date->copy()->endOfDay();

            if ($dayEnd <= $dayStart) {
                continue;
            }

            $weekend = $date->isWeekend();
            $quantity = $weekend
                ? mt_rand(2, 5)
                : mt_rand(3, 7);

            // Garante movimento nos últimos sete dias, independente do sorteio.
            if ($day >= self::HISTORY_DAYS - 7) {
                $quantity = max($quantity, 4);
            }

            for ($index = 0; $index < $quantity; $index++) {
                /** @var Charger $charger */
                $charger = $chargers[$index % $chargers->count()];
                /** @var User $driver */
                $driver = $drivers[mt_rand(0, $drivers->count() - 1)];

                $startedAt = Carbon::createFromTimestamp(mt_rand($dayStart->timestamp, $dayEnd->timestamp));
                $energy = ($index === 0 && $day >= self::HISTORY_DAYS - 7)
                    ? $this->randomFloat(18, 55, 1)
                    : $this->randomFloat(5, 62, 1);

                $energy = round($energy, 2);
                $gross = round($energy * $tariff, 2);
                $discount = mt_rand(1, 100) <= 12 ? round($gross * 0.10, 2) : 0.0;
                $amount = round($gross - $discount, 2);
                $duration = max(10, (int) round($energy / ((float) $charger->power_kw * 0.55) * 60));
                $endedAt = $startedAt->copy()->addMinutes($duration);

                // Sessões de hoje não podem terminar no futuro.
                if ($endedAt->isFuture()) {
                    $endedAt = now();
                    $startedAt = $endedAt->copy()->subMinutes($duration);
                }

                // A primeira sessão de cada dia é sempre concluída, garantindo
                // que os gráficos de energia e receita nunca fiquem vazios.
                $status = $index === 0
                    ? ChargingSession::STATUS_COMPLETED
                    : $this->randomElement([
                        ChargingSession::STATUS_COMPLETED,
                        ChargingSession::STATUS_COMPLETED,
                        ChargingSession::STATUS_COMPLETED,
                        ChargingSession::STATUS_COMPLETED,
                        ChargingSession::STATUS_PENDING,
                        ChargingSession::STATUS_CANCELLED,
                    ]);

                $paymentMethod = $this->randomElement([
                    ChargingSession::PAYMENT_WALLET,
                    ChargingSession::PAYMENT_WALLET,
                    ChargingSession::PAYMENT_WALLET,
                    ChargingSession::PAYMENT_POINTS,
                    ChargingSession::PAYMENT_SIMULATED,
                ]);

                $paymentStatus = match ($status) {
                    ChargingSession::STATUS_COMPLETED => 'paid',
                    ChargingSession::STATUS_PENDING => 'pending',
                    default => 'cancelled',
                };

                $paidAt = null;
                if ($status === ChargingSession::STATUS_COMPLETED) {
                    $paidAt = $endedAt->copy()->addMinutes(mt_rand(1, 30));
                    if ($paidAt->isFuture()) {
                        $paidAt = now();
                    }
                }

                $rows[] = [
                    'user_id' => $driver->id,
                    'establishment_id' => $establishment->id,
                    'charger_id' => $charger->id,
                    'charger_name' => $charger->name,
                    'tariff_per_kwh' => number_format($tariff, 4, '.', ''),
                    'energy_kwh' => number_format($status === ChargingSession::STATUS_CANCELLED ? 0 : $energy, 2, '.', ''),
                    'measured_energy_kwh' => number_format($status === ChargingSession::STATUS_CANCELLED ? 0 : $energy, 4, '.', ''),
                    'original_amount' => number_format($gross, 2, '.', ''),
                    'discount_amount' => number_format($discount, 2, '.', ''),
                    'amount_charged' => number_format($status === ChargingSession::STATUS_CANCELLED ? 0 : $amount, 2, '.', ''),
                    'payment_method' => $paymentMethod,
                    'status' => $status,
                    'charging_state' => 'ended',
                    'payment_status' => $paymentStatus,
                    'last_meter_sequence' => mt_rand(1, 500),
                    'started_at' => $startedAt->format('Y-m-d H:i:s'),
                    'ended_at' => $endedAt->format('Y-m-d H:i:s'),
                    'last_meter_at' => $endedAt->format('Y-m-d H:i:s'),
                    'paid_at' => $paidAt?->format('Y-m-d H:i:s'),
                    'is_demo' => true,
                    'created_at' => $startedAt->format('Y-m-d H:i:s'),
                    'updated_at' => $endedAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Inserção em blocos: o boot do Render não pode ficar preso em ~800
        // comandos individuais (create + save) durante a primeira carga.
        foreach (array_chunk($rows, 100) as $chunk) {
            ChargingSession::insert($chunk);
        }

        return count($rows);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function randomElement(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function randomFloat(float $min, float $max, int $decimals): float
    {
        return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), $decimals);
    }

    private function forgetDashboardCache(Establishment $establishment): void
    {
        foreach ([7, 30, 90] as $period) {
            foreach (['with-test', 'real-only'] as $environment) {
                Cache::forget(sprintf(
                    'manager:dashboard:%d:%s:%d:%s',
                    $establishment->id,
                    now()->toDateString(),
                    $period,
                    $environment
                ));
            }
        }
    }
}
