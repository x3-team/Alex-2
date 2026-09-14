<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->onDelete('cascade');
            $table->string('visitor_id'); // UUID из cookie
            $table->tinyInteger('rating'); // 1-5
            $table->timestamps();

            $table->unique(['blog_id', 'visitor_id']); // Один пользователь = одна оценка
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ratings');
    }
};