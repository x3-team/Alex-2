<?php

use App\Support\HomeSlideCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('home_settings', 'why_title')) {
                $table->string('why_title')->nullable()->after('hero_subtitle');
            }
            if (! Schema::hasColumn('home_settings', 'results_intro_title')) {
                $table->string('results_intro_title')->nullable()->after('why_subtitle');
            }
        });

        // Seed with the wording production shows right now, so the page does not
        // change on release. Anything the editor already filled in is left alone.
        foreach (['patient', 'doctor'] as $audience) {
            $row = DB::table('home_settings')->where('type', $audience)->first();
            if (! $row) {
                continue;
            }

            $missing = HomeSlideCopy::fillMissing((array) $row, $audience);
            if ($missing !== []) {
                DB::table('home_settings')->where('id', $row->id)->update($missing);
            }
        }
    }

    public function down(): void
    {
        Schema::table('home_settings', function (Blueprint $table) {
            foreach (['why_title', 'results_intro_title'] as $column) {
                if (Schema::hasColumn('home_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
