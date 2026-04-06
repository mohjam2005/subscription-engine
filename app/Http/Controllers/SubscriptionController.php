<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    
    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }
    
     
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'payment_token' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $subscription = $this->subscriptionService->createSubscription(
                $request->user()->id,
                $request->plan_id,
                $request->payment_token
            );
            
            return response()->json([
                'success' => true,
                'message' => 'تم انشاء الاشتراك بنجاح',
                'data' => $subscription->load('plan')
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    
    public function mySubscription(Request $request)
    {
        $subscription = $request->user()->subscription;
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد اشتراك نشط'
            ], 404);
        }
        
        $daysLeft = 0;
        if ($subscription->status === 'past_due' && $subscription->grace_period_ends_at) {
            $daysLeft = now()->diffInDays($subscription->grace_period_ends_at, false);
        } elseif ($subscription->status === 'active' && $subscription->current_period_ends_at) {
            $daysLeft = now()->diffInDays($subscription->current_period_ends_at, false);
        } elseif ($subscription->status === 'trialing' && $subscription->trial_ends_at) {
            $daysLeft = now()->diffInDays($subscription->trial_ends_at, false);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'user_name' => $request->user()->name,
                'plan_name' => $subscription->plan->name,
                'plan_price' => $subscription->plan->formatted_price ?? $subscription->plan->price,
                'status' => $subscription->status,
                'has_access' => $subscription->hasAccess(),
                'days_left' => $daysLeft > 0 ? $daysLeft : 0,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'grace_period_ends_at' => $subscription->grace_period_ends_at,
                'canceled_at' => $subscription->canceled_at,
            ]
        ]);
    }
    
    
    public function show($id)
    {
        $subscription = Subscription::with(['user', 'plan'])->find($id);
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'الاشتراك غير موجود'
            ], 404);
        }
        
        $daysLeft = 0;
        if ($subscription->status === 'past_due' && $subscription->grace_period_ends_at) {
            $daysLeft = now()->diffInDays($subscription->grace_period_ends_at, false);
        } elseif ($subscription->status === 'active' && $subscription->current_period_ends_at) {
            $daysLeft = now()->diffInDays($subscription->current_period_ends_at, false);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'user' => $subscription->user->name ?? 'Unknown',
                'user_email' => $subscription->user->email ?? 'Unknown',
                'plan' => $subscription->plan->name,
                'status' => $subscription->status,
                'has_access' => $subscription->hasAccess(),
                'days_left' => $daysLeft > 0 ? $daysLeft : 0,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'grace_period_ends_at' => $subscription->grace_period_ends_at,
            ]
        ]);
    }
    
    
    public function renew(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_token' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $subscription = Subscription::find($id);
        
        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }
        
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك تجديد اشتراك ليس لك'], 403);
        }
        
        $paymentSuccess = rand(1, 100) <= 80;
        
        if ($paymentSuccess) {
            $subscription->status = 'active';
            $subscription->grace_period_ends_at = null;
            
            // تجميع الفترات: التمديد من تاريخ الانتهاء الحالي وليس من الآن
            $fromDate = $subscription->current_period_ends_at ?? now();
            $subscription->current_period_ends_at = $this->calculateNextBillingDateFromDate($subscription->plan, $fromDate);
            
            $subscription->save();
            
            return response()->json([
                'success' => true,
                'message' => 'تم تجديد الاشتراك بنجاح',
                'data' => $subscription->load('plan')
            ]);
        }
        
        $subscription->status = 'past_due';
        $subscription->grace_period_ends_at = now()->addDays(3);
        $subscription->save();
        
        return response()->json([
            'success' => false,
            'message' => 'فشل الدفع. دخلت في فترة سماح مدتها 3 ايام',
            'data' => $subscription->load('plan')
        ], 402);
    }
    
    /**
     * حساب تاريخ الفاتورة القادمة من تاريخ محدد (تجميع الفترات)
     */
    private function calculateNextBillingDateFromDate($plan, $fromDate)
    {
        $date = $fromDate instanceof Carbon ? $fromDate : Carbon::parse($fromDate);
        
        switch ($plan->billing_cycle) {
            case 'monthly':
                return $date->addMonth();
            case 'yearly':
                return $date->addYear();
            case 'weekly':
                return $date->addWeek();
            default:
                return $date->addMonth();
        }
    }
    
   
    public function cancel(Request $request, $id)
    {
        $subscription = Subscription::find($id);
        
        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }
        
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك إلغاء اشتراك ليس لك'], 403);
        }
        
        $subscription = $this->subscriptionService->cancelSubscription($id);
        
        return response()->json([
            'success' => true,
            'message' => 'تم الغاء الاشتراك',
            'data' => $subscription
        ]);
    }
    
    
    public function all()
    {
        $subscriptions = Subscription::with(['user', 'plan'])->get();
        
        return response()->json([
            'success' => true,
            'message' => 'جميع المشتركين',
            'count' => $subscriptions->count(),
            'data' => $subscriptions
        ]);
    }
    
   
    public function retryPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_token' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $subscription = Subscription::find($id);
        
        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }
        
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك اعادة محاولة الدفع لاشتراك ليس لك'], 403);
        }
        
        try {
            $subscription = $this->subscriptionService->retryPayment($id, $request->payment_token);
            
            return response()->json([
                'success' => true,
                'message' => 'تم الدفع بنجاح وعاد الاشتراك نشطًا',
                'data' => $subscription->load('plan')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}