<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_url')->unique()->index();
            $table->string('to_url');
            $table->integer('status_code')->default(301); // 301 или 302
            $table->boolean('is_active')->default(true);
            $table->integer('hits_count')->default(0); // Счётчик срабатываний
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};