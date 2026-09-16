<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('blogs', 'noindex')) {
                $table->boolean('noindex')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('blogs', 'og_title')) {
                $table->string('og_title')->nullable()->after('seo_keywords');
            }
            if (!Schema::hasColumn('blogs', 'og_description')) {
                $table->string('og_description', 500)->nullable()->after('og_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['noindex', 'og_title', 'og_description'],
                fn ($c) => Schema::hasColumn('blogs', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
