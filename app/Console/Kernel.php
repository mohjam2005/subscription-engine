<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\CheckExpiredTrials::class,
        Commands\ProcessGracePeriod::class,
    ];
    
    protected function schedule(Schedule $schedule)
    {
        // شغال كل يوم على الساعة 2 صباحًا
        $schedule->command('subscription:check-expired-trials')->dailyAt('02:00');
        $schedule->command('subscription:process-grace-period')->dailyAt('02:05');
        
        // للاختبار: لو عايز تشغله كل دقيقة
        // $schedule->command('subscription:check-expired-trials')->everyMinute();
    }
}