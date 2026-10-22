<?php

namespace App\Console\Commands;

use App\Models\Establishment;
use App\Services\DemandAlertService;
use App\Services\DemandControlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonitorDemandPeaks extends Command
{
    protected $signature = 'demand:monitor-peaks
        {--establishment= : ID do estabelecimento para testar}';

    protected $description = 'Monitora picos de recarga e demanda elétrica dos estabelecimentos';

    public function handle(
        DemandAlertService $alertService,
        DemandControlService $demandService
    ): int {
        $query = Establishment::query();

        if ($this->option('establishment')) {
            $query->whereKey($this->option('establishment'));
        }

        $total = 0;
        $synchronized = 0;
        $created = 0;
        $resolved = 0;
        $errors = 0;

        $query->orderBy('id')->chunkById(
            100,
            function ($establishments) use (
                $alertService,
                $demandService,
                &$total,
                &$synchronized,
                &$created,
                &$resolved,
                &$errors
            ) {
                foreach ($establishments as $establishment) {
                    $total++;

                    $syncSucceeded = false;

                    try {
                        $readings = $demandService
                            ->syncReadingsFromFirestore($establishment);

                        $synchronized += $readings;
                        $syncSucceeded = true;
                    } catch (Throwable $exception) {
                        $errors++;

                        $this->error(
                            "Erro ao sincronizar estabelecimento {$establishment->id}: {$exception->getMessage()}"
                        );

                        Log::error('Erro ao sincronizar leituras de demanda', [
                            'establishment_id' => $establishment->id,
                            'exception' => $exception,
                        ]);
                    }

                    try {
                        $result = $alertService->check($establishment);

                        $created += $result['created'];
                        $resolved += $result['resolved'];
                    } catch (Throwable $exception) {
                        $errors++;

                        $this->error(
                            "Erro ao verificar estabelecimento {$establishment->id}: {$exception->getMessage()}"
                        );

                        Log::error('Erro ao monitorar picos de demanda', [
                            'establishment_id' => $establishment->id,
                            'sync_succeeded' => $syncSucceeded,
                            'exception' => $exception,
                        ]);
                    }
                }
            }
        );

        $this->info("Estabelecimentos verificados: {$total}");
        $this->info("Leituras sincronizadas: {$synchronized}");
        $this->info("Alertas criados: {$created}");
        $this->info("Alertas encerrados: {$resolved}");
        $this->info("Erros encontrados: {$errors}");

        return $errors > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
