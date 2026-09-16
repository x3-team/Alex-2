<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'value')) {
            return;
        }

        // SQLite (CI tests) stores arbitrary text length in TEXT columns.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->longText('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'value')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }
};
