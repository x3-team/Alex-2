<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            // Удаляем старый внешний ключ
            $table->dropForeign(['user_id']);

            // Делаем поле nullable и меняем поведение на set null
            $table->foreignId('user_id')
                ->nullable()
                ->change()
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            // Возвращаем старое поведение
            $table->dropForeign(['user_id']);

            $table->foreignId('user_id')
                ->nullable(false)
                ->change()
                ->constrained('users')
                ->onDelete('cascade');
        });
    }
};