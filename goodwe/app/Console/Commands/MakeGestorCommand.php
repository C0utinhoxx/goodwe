<?php

namespace App\Console\Commands;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeGestorCommand extends Command
{
    protected $signature = 'user:make-gestor {email}
        {--password= : Senha para criar o usuario (obrigatorio se nao existir)}
        {--seed : Cria um estabelecimento e sessoes de teste para o gestor}';

    protected $description = 'Cria um usuario gestor (manager) por e-mail. Se nao existir, cria com a senha informada.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password');
        $seed = $this->option('seed');

        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! $password) {
                $password = $this->secret('Usuario nao encontrado. Informe a senha para criar o usuario:');
            }


            if (strlen($password) < 6) {
                $this->error('A senha deve ter pelo menos 6 caracteres.');

                return Command::FAILURE;
            }

            $name = $this->ask('Nome do usuario') ?? explode('@', $email)[0];

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);

            $this->info("Usuario '{$user->name}' ({$email}) criado e promovido a gestor com sucesso!");

            if ($seed) {
                $this->seedDashboard($user);
            }

            return Command::SUCCESS;
        }

        if (! $user->is_admin) {
            $user->update(['is_admin' => true]);
            $this->info("Usuario '{$user->name}' ({$email}) foi promovido a gestor com sucesso!");
        } else {
            $this->warn("O usuario '{$user->name}' ja e um gestor.");
        }

        if ($seed) {
            $this->seedDashboard($user);
        }

        return Command::SUCCESS;
    }

    private function seedDashboard(User $user): void
    {
        $establishment = Establishment::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Estabelecimento - '.$user->name,
                'address' => 'Endereco nao definido',
                'type' => Establishment::TYPE_COMERCIAL,
                'operating_hours' => '08:00 - 22:00',
                'cost_per_kwh' => 1.5000,
            ]
        );

        $existingSessions = ChargingSession::where('establishment_id', $establishment->id)->count();

        if ($existingSessions > 0) {
            $this->warn("O establishment ja possui {$existingSessions} sessoes. Nenhuma sessao adicional criada.");

            return;
        }

        $faker = Factory::create('pt_BR');

        $chargerNames = ['Torre A - Norte', 'Torre A - Sul', 'Estadio - Setor B', 'Garagem Empresarial', 'Patio Central'];
        $statuses = ['completed', 'completed', 'completed', 'completed', 'pending', 'cancelled'];
        $paymentMethods = ['wallet', 'wallet', 'wallet', 'points', 'simulated'];

        $users = User::where('id', '!=', $user->id)->pluck('id')->toArray();

        if (empty($users)) {
            $fakeUser = User::create([
                'name' => 'Carlos Silva',
                'email' => 'carlos@email.com',
                'password' => Hash::make('123456'),
            ]);
            $users = [$fakeUser->id];
        }

        $startDate = now()->subDays(30);

        for ($i = 0; $i < 60; $i++) {
            $createdAt = $faker->dateTimeBetween($startDate, now());
            $energyKwh = round($faker->randomFloat(1, 5, 65), 1);
            $amount = round($energyKwh * $establishment->cost_per_kwh, 2);
            $status = $faker->randomElement($statuses);

            ChargingSession::create([
                'user_id' => $faker->randomElement($users),
                'establishment_id' => $establishment->id,
                'charger_name' => $faker->randomElement($chargerNames),
                'energy_kwh' => $energyKwh,
                'amount_charged' => $amount,
                'payment_method' => $faker->randomElement($paymentMethods),
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->info("Estabelecimento '{$establishment->name}' criado com 60 sessoes de teste!");
    }
}
