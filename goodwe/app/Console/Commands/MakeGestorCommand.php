<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeGestorCommand extends Command
{
    protected $signature = 'user:make-gestor {email} {--password= : Senha para criar o usuario (obrigatorio se nao existir)}';

    protected $description = 'Cria um usuario gestor (manager) por e-mail. Se nao existir, cria com a senha informada.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password');

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

            return Command::SUCCESS;
        }

        if ($user->is_admin) {
            $this->warn("O usuario '{$user->name}' ja e um gestor.");

            return Command::SUCCESS;
        }

        $user->update(['is_admin' => true]);

        $this->info("Usuario '{$user->name}' ({$email}) foi promovido a gestor com sucesso!");

        return Command::SUCCESS;
    }
}
