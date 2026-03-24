<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StoreActivityLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $tenantDatabase,
        private array $data
    ) {}

    public function handle(): void
    {
        if (empty($this->tenantDatabase)) {
            return;
        }

        config(['database.connections.tenant.database' => $this->tenantDatabase]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        ActivityLog::create($this->data);
    }
}
