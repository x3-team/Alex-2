<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            // 🔹 Содержание (оглавление) - выше контента
            $table->text('table_of_contents')->nullable()->after('excerpt');
            
            // 🔹 Источники - ниже контента
            $table->text('sources')->nullable()->after('content');
            
            // 🔹 Блок СТА (призыв к действию)
            $table->string('cta_title')->nullable()->after('sources');
            $table->text('cta_description')->nullable()->after('cta_title');
            $table->string('cta_button_text')->nullable()->after('cta_description');
            $table->string('cta_button_url')->nullable()->after('cta_button_text');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn([
                'table_of_contents',
                'sources',
                'cta_title',
                'cta_description',
                'cta_button_text',
                'cta_button_url',
            ]);
        });
    }
};