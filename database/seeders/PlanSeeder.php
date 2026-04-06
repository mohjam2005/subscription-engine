<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run()
    {
        Plan::create([
            'name' => 'الباقة الأساسية',
            'description' => 'مناسبة للبدء',
            'billing_cycle' => 'monthly',
            'price' => 49.99,
            'currency' => 'AED',
            'trial_days' => 7,
            'is_active' => true,
        ]);
        
        Plan::create([
            'name' => 'الباقة الاحترافية',
            'description' => 'للمحترفين',
            'billing_cycle' => 'yearly',
            'price' => 499.99,
            'currency' => 'USD',
            'trial_days' => 14,
            'is_active' => true,
        ]);
        
        Plan::create([
            'name' => 'الباقة المميزة',
            'description' => 'كل المميزات',
            'billing_cycle' => 'monthly',
            'price' => 299.99,
            'currency' => 'EGP',
            'trial_days' => 3,
            'is_active' => true,
        ]);
    }
}