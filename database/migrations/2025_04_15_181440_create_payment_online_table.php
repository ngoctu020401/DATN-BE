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
        Schema::create('payment_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('vnp_transaction_no')->nullable();
            $table->string('vnp_bank_code')->nullable();
            $table->string('vnp_bank_tran_no')->nullable();
            $table->timestamp('vnp_pay_date')->nullable();
            $table->string('vnp_card_type')->nullable();
            $table->string('vnp_response_code')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_online');
    }
};
