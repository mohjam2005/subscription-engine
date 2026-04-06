<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function userHasActiveSubscription($userId)
{
    $subscription = Subscription::where('user_id', $userId)
        ->whereIn('status', ['trialing', 'active', 'past_due'])
        ->first();
    
    return !is_null($subscription);
}
    /**
     * إنشاء اشتراك جديد
     * احمد 10/4/2026: لازم نضيف validation للـ user اذا كان عنده اشتراك active قبل كده
     */
    public function createSubscription($userId, $planId, $paymentToken = null)
    {
         // التحقق من عدم وجود اشتراك نشط
        if ($this->userHasActiveSubscription($userId)) {
            throw new \Exception('لديك اشتراك نشط بالفعل. قم بإلغائه أولاً أو انتظر حتى ينتهي.');
        }
        $plan = Plan::findOrFail($planId);
        // لو الخطة مش فعالة او غير متاحة 
        if (!$plan->is_active) {
            throw new \Exception('هذه الخطة غير متاحة حاليًا');
        }
        
        DB::beginTransaction();
        
        try {
            $subscription = new Subscription();
            $subscription->user_id = $userId;
            $subscription->plan_id = $planId;
            
            // منطق الفترة التجريبية
            if ($plan->trial_days > 0) {
                $subscription->status = 'trialing';
                /// هنا استخدمنا كاربون لتخزين التاريخ خلال الايام 
                $subscription->trial_ends_at = Carbon::now()->addDays($plan->trial_days);
                $subscription->current_period_ends_at = $subscription->trial_ends_at;
                // مش محتاج دفع دلوقتي
            } else {
                // للخطط بدون تجربة: نحاول نديب
                $paymentSuccess = $this->chargeCustomer($userId, $plan->price, $paymentToken);
                
                if ($paymentSuccess) {
                    $subscription->status = 'active';
                    $subscription->current_period_ends_at = $this->calculateNextBillingDate($plan);
                } else {
                    throw new \Exception('عملية الدفع فشلت، يرجى المحاولة مرة أخرى');
                }
            }
            
            $subscription->save();
            
            DB::commit();
            
            // تسجيل نجاح
            Log::info('New subscription created', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => $subscription->status
            ]);
            
            return $subscription;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * معالجة فشل الدفع (تحويل لـ past_due)
     * ممكن نحسنها بعدين ونضيف عدد محاولات
     */
    public function handlePaymentFailure($subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        
         if (in_array($subscription->status, ['canceled'])) {
            return $subscription;
        }
        
        $subscription->status = 'past_due';
        $subscription->grace_period_ends_at = Carbon::now()->addDays(3); // 3 ايام سماح
        
        $subscription->save();
        
        // TODO: بعت ايميل للمستخدم انه الدفع فشل
        // Mail::to($subscription->user->email)->send(...);
        
        Log::warning("Subscription #{$subscriptionId} is now past_due", [
            'grace_period_ends_at' => $subscription->grace_period_ends_at
        ]);
        
        return $subscription;
    }
    
    /**
     * اعادة محاولة الدفع (أثناء past_due)
     * محمد 12/4: اتأكد اني ما اعدش اضرب grace period اكتر من مرة
     */
    public function retryPayment($subscriptionId, $paymentToken)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        
        if ($subscription->status !== 'past_due') {
            throw new \Exception('لا يمكن اعادة المحاولة للاشتراكات غير المتأخرة');
        }
        
        // شيك لو فترة السماح خلصت
        if ($subscription->grace_period_ends_at && Carbon::now()->greaterThan($subscription->grace_period_ends_at)) {
            throw new \Exception('فترة السماح انتهت، يرجى انشاء اشتراك جديد');
        }
        
        // نحاول نديب
        $paymentSuccess = $this->chargeCustomer($subscription->user_id, $subscription->plan->price, $paymentToken);
        
        if ($paymentSuccess) {
            $subscription->status = 'active';
            $subscription->grace_period_ends_at = null;
            $subscription->current_period_ends_at = $this->calculateNextBillingDate($subscription->plan);
            $subscription->save();
            
            Log::info("Subscription #{$subscriptionId} reactivated after successful payment");
            
            return $subscription;
        }
        
        // فشل مرة تانية: نقدر نضيف محاولة فاشلة لو عايزين
        // بس حاليًا مش هنعمل حاجة
        throw new \Exception('فشل الدفع مرة أخرى، يرجى التحقق من طريقة الدفع');
    }
    
    /**
     * اعلان الاشتراك
     * ممكن نضيف support لـ cancel at period end
     */
    public function cancelSubscription($subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        
        $subscription->status = 'canceled';
        $subscription->canceled_at = Carbon::now();
        $subscription->save();
        
        Log::info("Subscription #{$subscriptionId} was canceled by user");
        
        return $subscription;
    }
    
    /**
     * حساب تاريخ الفاتورة القادمة
     * فين اسبوع؟ لو billing cycle weekly مضيفتش لسه!
     */
    public  function calculateNextBillingDate($plan)
    {
        $now = Carbon::now();
        
        switch ($plan->billing_cycle) {
            case 'monthly':
                return $now->addMonth();
            case 'yearly':
                return $now->addYear();
            case 'weekly':
                return $now->addWeek(); // اضفتها دلوقتي عشان كنت ناسيها
            default:
                return $now->addMonth(); // default monthly
        }
    }
    
    /**
     * محاكاة عملية الدفع
     * في الحقيقة هنستدعي Stripe أو PayPal
     * انا حطيت random عشان اختبر الحالات المختلفة
     */
    private function chargeCustomer($userId, $amount, $paymentToken)
    {
        // للاسف مش موصل للبوابة الحقيقية
        // هنعمل simulation
        
        // 85% نجاح, 15% فشل
        $isSuccessful = rand(1, 100) <= 85;
        
        if ($isSuccessful) {
            Log::info("Payment successful for user {$userId}: {$amount}");
            return true;
        }
        
        Log::warning("Payment failed for user {$userId}: {$amount}");
        return false;
    }
}