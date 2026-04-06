<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;  
 
class Plan extends Model
{
    protected $fillable = [
        'name', 'description', 'billing_cycle', 
        'price', 'currency', 'trial_days', 'is_active'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'trial_days' => 'integer'
    ];
    
    // العلاقات
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
    // هل الخطة شهرية؟
    public function isMonthly()
    {
        return $this->billing_cycle === 'monthly';
    }
    
    // دوال مساعدة سريعة
    public function getFormattedPriceAttribute()
    {
        return $this->currency . ' ' . number_format($this->price, 2);
    }
}