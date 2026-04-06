// database/migrations/2024_01_01_000002_create_subscriptions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default('trialing');  
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();  
            $table->timestamp('canceled_at')->nullable();
            
             // $table->foreignId('payment_method_id')->nullable();
            
            $table->timestamps();
            
            // سويت اندكس عشان الكرون يجيب البيانات بسرعة
            $table->index(['status', 'grace_period_ends_at']);
            $table->index(['status', 'trial_ends_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};