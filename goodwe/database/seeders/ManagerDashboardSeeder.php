<?php

namespace Database\Seeders;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerDashboardSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = $this->command->option('email') ?? 'brunoriquelme2801@icloud.com';

        $gestor = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => explode('@', $email)[0],
                'password' => Hash::make('123456'),
                'is_admin' => true,
            ]
        );

        if (! $gestor->is_admin) {
            $gestor->update(['is_admin' => true]);
        }

        $establishment = Establishment::firstOrCreate(
            ['user_id' => $gestor->id],
            [
                'name' => 'Shopping Center Norte',
                'address' => 'Av. Av. Cruzeiro do Sul, 1800 - Santana, Sao Paulo - SP',
                'type' => 'shopping',
                'operating_hours' => '10:00 - 22:00',
                'phone' => '(11) 3456-7890',
                'cost_per_kwh' => 1.8500,
            ]
        );

        $existingSessions = ChargingSession::where('establishment_id', $establishment->id)->count();

        if ($existingSessions > 0) {
            $this->command->warn("Estabelecimento ja possui {$existingSessions} sessoes. Pulando criacao de dados.");

            return;
        }

        $chargerNames = [
            'Torre A - Norte',
            'Torre A - Sul',
            'Estadio - Setor B',
            'Garagem Empresarial',
            'Patio Central',
        ];

        $statuses = ['completed', 'completed', 'completed', 'completed', 'pending', 'cancelled'];
        $paymentMethods = ['wallet', 'wallet', 'wallet', 'points', 'simulated'];

        $users = User::where('id', '!=', $gestor->id)->pluck('id')->toArray();

        if (empty($users)) {
            $fakeUser = User::create([
                'name' => 'Carlos Silva',
                'email' => 'carlos@email.com',
                'password' => Hash::make('123456'),
            ]);
            $users = [$fakeUser->id];

            User::create([
                'name' => 'Ana Santos',
                'email' => 'ana@email.com',
                'password' => Hash::make('123456'),
            ]);
            $users[] = User::where('email', 'ana@email.com')->first()->id;
        }

        $faker = Factory::create('pt_BR');

        $startDate = now()->subDays(29)->startOfDay();

        for ($i = 0; $i < 60; $i++) {
            $createdAt = $faker->dateTimeBetween($startDate, now());
            $energyKwh = round($faker->randomFloat(1, 5, 65), 1);
            $amount = round($energyKwh * $establishment->cost_per_kwh, 2);
            $status = $faker->randomElement($statuses);

            $session = ChargingSession::create([
                'user_id' => $faker->randomElement($users),
                'establishment_id' => $establishment->id,
                'charger_name' => $faker->randomElement($chargerNames),
                'energy_kwh' => $energyKwh,
                'amount_charged' => $amount,
                'payment_method' => $faker->randomElement($paymentMethods),
                'status' => $status,
            ]);

            $session->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        $this->command->info('Dados de gestor criados com sucesso!');
        $this->command->info("Email: {$email}");
        $this->command->info("Estabelecimento: {$establishment->name}");
        $this->command->info('Sessoes criadas: 60 registros nos ultimos 30 dias');
    }
}
