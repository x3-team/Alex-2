<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('cart_settings', function (Blueprint $table) {
            $table->id();

            // Товар 1: ALEX2 (Всегда активен)
            $table->string('alex2_title')->default('Сдача анализа ALEX2');
            $table->text('alex2_description')->nullable();
            $table->decimal('alex2_price', 10, 2)->default(0);
            $table->boolean('alex2_is_active')->default(true);

            // Товар 2: Консультация
            $table->string('consultation_title')->default('Консультация специалиста');
            $table->text('consultation_description')->nullable();
            $table->decimal('consultation_price', 10, 2)->default(0);
            $table->boolean('consultation_is_active')->default(true);

            // Товар 3: Срочный результат
            $table->string('urgent_title')->default('Срочный результат (24 часа)');
            $table->text('urgent_description')->nullable();
            $table->decimal('urgent_price', 10, 2)->default(0);
            $table->boolean('urgent_is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cart_settings');
    }
};