<?php

namespace App\Console\Commands;

use App\Models\GenieacsServer;
use App\Services\OntSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('genieacs:sync-onts {serverId? : ID server GenieACS tertentu}')]
#[Description('Sinkronkan snapshot ONT read-only dari GenieACS ke database lokal')]
class SyncGenieAcsOnts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OntSyncService $syncService): int
    {
        $query = GenieacsServer::query()->where('is_active', true);

        if ($serverId = $this->argument('serverId')) {
            $query->whereKey($serverId);
        }

        $servers = $query->get();

        if ($servers->isEmpty()) {
            $this->warn('Tidak ada server GenieACS aktif yang cocok.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($servers as $server) {
            try {
                $result = $syncService->sync($server);
                $this->info(
                    "{$server->name}: {$result['processed']} diproses, ".
                    "{$result['created']} baru, {$result['updated']} diperbarui, ".
                    "{$result['missing']} missing."
                );
            } catch (Throwable $exception) {
                $failed = true;
                $server->forceFill([
                    'connection_status' => 'failed',
                    'last_error_at' => now(),
                    'last_error_message' => str($exception->getMessage())->limit(1000),
                ])->save();
                $this->error("{$server->name}: sinkronisasi gagal.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
