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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('discount_percent')->nullable();
            $table->integer('amount')->nullable();
            $table->integer('max_discount_amount')->nullable();
            $table->integer('min_product_price')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('times_used')->default(0);
            $table->boolean('is_active')->default(true); // admin tắt voucher bất kỳ lúc nào
            $table->enum('type', ['percent', 'amount']);
            $table->datetime('expiry_date')->nullable();
            $table->datetime('start_date');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
