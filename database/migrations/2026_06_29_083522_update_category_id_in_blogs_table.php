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
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropForeign(['category_id']); // Удаляем старый ключ
            $table->foreignId('category_id')->nullable()->change(); // Делаем nullable
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null'); // Добавляем новый ключ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            //
        });
    }
};
