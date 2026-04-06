<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredTrials extends Command
{
    protected $signature = 'subscription:check-expired-trials';
    protected $description = 'تحديد الفترات التجريبية المنتهية وتحويلها إلى past_due';
    
    public function handle()
    {
        $this->info('جاري معالجة الفترات التجريبية المنتهية...');
        
        // جلب الاشتراكات اللي حالتها trialing وانتهت صلاحيتها
        $expiredTrials = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<', Carbon::now())
            ->get();
        
        $count = 0;
        
        foreach ($expiredTrials as $subscription) {
            // حولها لـ past_due عشان ندي فرصة
            $subscription->status = 'past_due';
            $subscription->grace_period_ends_at = Carbon::now()->addDays(3);
            $subscription->save();
            
            $count++;
            $this->line("Subscription #{$subscription->id}: trial expired -> past_due");
            
            // TODO: ارسال ايميل للمستخدم انه يدفع
        }
        
        $this->info("تم معالجة {$count} اشتراك");
        Log::info("Cron: Processed {$count} expired trials");
    }
}