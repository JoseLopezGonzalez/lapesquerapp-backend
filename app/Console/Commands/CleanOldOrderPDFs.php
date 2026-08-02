<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanOldOrderPDFs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-old-order-pdfs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = storage_path('app/public');
        $files = glob($directory.'/*.pdf');

        $now = time();
        $maxAge = 3600; // 1 hora

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }

        $this->info('PDFs antiguos eliminados correctamente.');

    }
}
