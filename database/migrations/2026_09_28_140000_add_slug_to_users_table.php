<?php

use App\Support\AuthorSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        $used = [];
        $users = DB::table('users')->select('id', 'name')->orderBy('id')->get();
        foreach ($users as $user) {
            $base = AuthorSlug::fromName((string) $user->name);
            $candidate = $base;
            $i = 2;
            while (isset($used[$candidate])) {
                $candidate = $base.'-'.$i;
                $i++;
            }
            $used[$candidate] = true;
            DB::table('users')->where('id', $user->id)->update(['slug' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
