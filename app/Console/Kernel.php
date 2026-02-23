<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        \App\Console\Commands\MigrateTenants::class,
        \App\Console\Commands\DetectSuspiciousActivity::class,
        \App\Console\Commands\CheckOnboardingStuck::class,
        \App\Console\Commands\PruneLogs::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:clean-old-order-pdfs')->dailyAt('02:00');
        $schedule->command('auth:cleanup-magic-tokens')->dailyAt('03:00');
        $schedule->command('superadmin:detect-suspicious')->everyFifteenMinutes();
        $schedule->command('superadmin:check-onboarding-stuck')->hourly();
        $schedule->command('superadmin:prune-logs')->dailyAt('04:00');
    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
