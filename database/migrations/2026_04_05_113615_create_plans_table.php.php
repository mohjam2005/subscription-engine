// database/migrations/2024_01_01_000001_create_plans_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
             $table->string('billing_cycle'); 
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);  
            $table->integer('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
             $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
};