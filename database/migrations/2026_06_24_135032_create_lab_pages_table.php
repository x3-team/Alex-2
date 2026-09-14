<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'about', 'licenses', 'contacts'
            $table->string('title')->nullable();
            $table->longText('content')->nullable(); // HTML контент
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_pages');
    }
};