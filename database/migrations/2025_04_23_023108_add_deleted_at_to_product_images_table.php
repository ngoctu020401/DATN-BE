<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->softDeletes(); // thêm cột deleted_at
        });
    }
    
    public function down()
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
    
};
