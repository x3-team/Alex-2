<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            // Добавляем поле type с дефолтным значением 'patient'
            $table->string('type')->default('patient')->after('id');

            // Составной уникальный индекс, чтобы не было дублей типов
            $table->unique('type');
        });
    }

    public function down(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->dropColumn('type');
        });
    }
};