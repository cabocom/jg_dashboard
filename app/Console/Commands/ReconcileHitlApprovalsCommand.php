<?php

namespace App\Console\Commands;

use App\Services\Hitl\HitlApprovalService;
use Illuminate\Console\Command;

class ReconcileHitlApprovalsCommand extends Command
{
    protected $signature = 'hitl:reconcile {--ttl=120 : Tiempo de gracia en segundos en PROCESSING antes de restaurar a PENDING}';

    protected $description = 'Restaura a PENDING las solicitudes de aprobación HITL colgadas en PROCESSING por caída de worker o timeout de red';

    public function handle(HitlApprovalService $hitlService): int
    {
        $ttl = (int) $this->option('ttl');
        if ($ttl <= 0) {
            $ttl = 120;
        }

        $this->info("🔍 Reconciliando solicitudes HITL en PROCESSING con antigüedad superior a {$ttl}s...");

        $result = $hitlService->reconcileStaleProcessingApprovals($ttl);
        $count = $result['reconciled_count'];

        if ($count === 0) {
            $this->info('✅ No se detectaron solicitudes colgadas en PROCESSING.');

            return Command::SUCCESS;
        }

        $this->warn("⚠️  Se reconciliaron {$count} solicitud(es) colgada(s). Restauradas a PENDING:");
        foreach ($result['reconciled_ids'] as $id) {
            $this->line("   - ID: {$id}");
        }

        return Command::SUCCESS;
    }
}
