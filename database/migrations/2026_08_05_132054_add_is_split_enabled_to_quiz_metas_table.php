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
        Schema::table('quiz_metas', function (Blueprint $table) {
            // Добавляем флаг разделения (по умолчанию true)
            $table->boolean('is_split_enabled')->default(true)->after('keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_metas', function (Blueprint $table) {
            $table->dropColumn('is_split_enabled');
        });
    }
};