<?php

namespace App\Services;

use App\Models\Establishment;
use App\Models\TariffEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstablishmentService
{
    public function create(User $manager, array $data): Establishment
    {
        try {
            return DB::transaction(function () use ($manager, $data) {
                $locked = UserBalanceLock::acquire($manager->id);
                abort_unless($locked->isGestor(), 403);
                if ($locked->establishment()->exists()) {
                    throw ValidationException::withMessages(['establishment' => 'Sua conta já possui um estabelecimento.']);
                }
                $establishment = Establishment::create([...$data, 'user_id' => $locked->id]);
                TariffEvent::create(['establishment_id' => $establishment->id, 'actor_id' => $locked->id, 'type' => 'configuration_created', 'previous_tariff' => null, 'new_tariff' => $establishment->currentTariff(), 'percentage' => $establishment->surge_percentage, 'pricing_mode' => $establishment->pricing_mode, 'occurred_at' => now()]);

                return $establishment;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cnpj' => 'O CNPJ ou a conta do gestor já possui um estabelecimento cadastrado.']);
        }
    }
}
