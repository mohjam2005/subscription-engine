<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'trial_ends_at',
        'current_period_ends_at', 'grace_period_ends_at', 'canceled_at'
    ];
    
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];
    
    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    
    // هل الاشتراك لسه شغال؟
    public function isActive()
    {
        // انتبه: past_due لسه يعتبر active مؤقتًا
        return in_array($this->status, ['trialing', 'active', 'past_due']);
    }
    
    // هل يقدر المستخدم يدخل للنظام؟
    public function hasAccess()
    {
        if ($this->status === 'active') {
            return true;
        }
        
        if ($this->status === 'trialing') {
            // اتأكد انه التجربة لسه مخلصتش
            return $this->trial_ends_at && Carbon::now()->lessThan($this->trial_ends_at);
        }
        
        if ($this->status === 'past_due') {
            // فترة السماح: لسه قدامه فرصة
            return $this->grace_period_ends_at && Carbon::now()->lessThan($this->grace_period_ends_at);
        }
        
        return false;
    }
    
    // أيام متبقية في فترة السماح
    public function getGracePeriodDaysLeft()
    {
        if ($this->status !== 'past_due' || !$this->grace_period_ends_at) {
            return 0;
        }
        
        return Carbon::now()->diffInDays($this->grace_period_ends_at, false);
    }
}