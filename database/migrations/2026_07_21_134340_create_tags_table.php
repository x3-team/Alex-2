<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Сначала удаляем pivot-таблицу, если она есть
        if (Schema::hasTable('blog_tag')) {
            Schema::dropIfExists('blog_tag');
        }

        // Потом удаляем tags
        if (Schema::hasTable('tags')) {
            Schema::dropIfExists('tags');
        }

        // Создаём таблицу tags с нужными полями
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Создаём pivot-таблицу blog_tag
        Schema::create('blog_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained('tags')->cascadeOnDelete();
            $table->unique(['blog_id', 'blog_tag_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // При откате сначала удаляем pivot, потом tags
        Schema::dropIfExists('blog_tag');
        Schema::dropIfExists('tags');
    }
};