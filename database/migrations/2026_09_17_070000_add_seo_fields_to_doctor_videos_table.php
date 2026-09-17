<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_videos', function (Blueprint $table) {
            $table->string('seo_title', 255)->nullable()->after('description');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('seo_keywords', 500)->nullable()->after('seo_description');
            $table->string('og_title', 255)->nullable()->after('seo_keywords');
            $table->text('og_description')->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_videos', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_keywords',
                'og_title',
                'og_description',
            ]);
        });
    }
};
