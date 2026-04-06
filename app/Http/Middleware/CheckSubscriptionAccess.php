<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'غير مسجل دخول'], 401);
        }
        
        $subscription = $user->subscription; // لازم تعرف العلاقة في User model
        
        if (!$subscription || !$subscription->hasAccess()) {
            return response()->json([
                'message' => 'اشتراكك غير نشط. يرجى تجديد الاشتراك.',
                'status' => $subscription ? $subscription->status : 'no_subscription'
            ], 403);
        }
        
        return $next($request);
    }
}