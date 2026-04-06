<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessGracePeriod extends Command
{
    protected $signature = 'subscription:process-grace-period';
    protected $description = 'الغاء الاشتراكات اللي خلصت فترة السماح بتاعتها';
    
    public function handle()
    {
        $this->info('جاري معالجة فترة السماح...');
        
        // جلب الاشتراكات اللي حالتها past_due وخلصت فترة السماح
        $expiredGrace = Subscription::where('status', 'past_due')
            ->where('grace_period_ends_at', '<', Carbon::now())
            ->get();
        
        $count = 0;
        
        foreach ($expiredGrace as $subscription) {
            $subscription->status = 'canceled';
            $subscription->canceled_at = Carbon::now();
            $subscription->save();
            
            $count++;
            $this->warn("Subscription #{$subscription->id}: grace period ended -> CANCELED");
            
            // هنا المفروض نرسل ايميل ان الاشتراك اتلغى
        }
        
        $this->info("تم الغاء {$count} اشتراك");
        Log::info("Cron: Canceled {$count} subscriptions due to expired grace period");
    }
}