<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
    
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
    public function hasActiveSubscription()
    {
        $sub = $this->subscription;
        return $sub && in_array($sub->status, ['trialing', 'active', 'past_due']);
    }
}