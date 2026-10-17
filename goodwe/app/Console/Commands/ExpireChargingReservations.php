<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireChargingReservations extends Command
{
    protected $signature = 'charging:expire-reservations';

    protected $description = 'Expira reservas vencidas e registra ausências e penalidades configuradas';

    public function handle(ReservationService $service): int
    {
        $this->info($service->expireDue().' reservas expiradas.');

        return self::SUCCESS;
    }
}
