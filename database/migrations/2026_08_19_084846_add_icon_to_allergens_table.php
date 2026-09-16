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
        Schema::table('allergens', function (Blueprint $table) {
            // Добавляем nullable колонку icon после description
            $table->string('icon')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allergens', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};