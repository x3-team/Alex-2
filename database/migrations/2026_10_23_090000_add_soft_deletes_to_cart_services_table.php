<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Удалённая в админке допуслуга не стирается из БД: на неё смогут ссылаться заказы.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_services', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('cart_services', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
