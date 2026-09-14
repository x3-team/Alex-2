<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('home_settings', 'hero_title')) {
                $table->string('hero_title')->nullable()->after('meta_keywords');
            }
            if (!Schema::hasColumn('home_settings', 'hero_subtitle')) {
                $table->string('hero_subtitle', 500)->nullable()->after('hero_title');
            }
            if (!Schema::hasColumn('home_settings', 'why_subtitle')) {
                $table->string('why_subtitle', 500)->nullable()->after('hero_subtitle');
            }
            if (!Schema::hasColumn('home_settings', 'cta_text')) {
                $table->string('cta_text')->nullable()->after('why_subtitle');
            }
            if (!Schema::hasColumn('home_settings', 'cta_url')) {
                $table->string('cta_url', 500)->nullable()->after('cta_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['hero_title', 'hero_subtitle', 'why_subtitle', 'cta_text', 'cta_url'],
                fn ($c) => Schema::hasColumn('home_settings', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
