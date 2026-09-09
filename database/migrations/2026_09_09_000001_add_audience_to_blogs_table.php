<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->blogTableName();

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'audience')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('audience', 20)
                    ->default('patients')
                    ->index();
            });
        }

        DB::table($tableName)->whereNull('audience')->update(['audience' => 'patients']);
    }

    public function down(): void
    {
        $tableName = $this->blogTableName();

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'audience')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }

    protected function blogTableName(): string
    {
        if (Schema::hasTable('blogs')) {
            return 'blogs';
        }

        if (Schema::hasTable('posts')) {
            return 'posts';
        }

        return 'blogs';
    }
};
