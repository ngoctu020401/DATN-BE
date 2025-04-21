<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('order_code')->unique();
            $table->string('email');
            $table->string('phone');
            $table->string('name');
            $table->string('address');
            $table->text('note')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->text('payment_url')->nullable();
            $table->string('payment_method');
            $table->foreignId('order_status_id')->constrained('order_statuses');
            $table->foreignId('payment_status_id')->constrained('payment_statuses');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
