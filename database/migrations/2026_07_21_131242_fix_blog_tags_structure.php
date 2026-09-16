<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Переименовываем существующую таблицу blog_tags в tags
        if (Schema::hasTable('blog_tags')) {
            Schema::rename('blog_tags', 'tags');
        }

        // 2. Создаём pivot-таблицу blog_tag для связи blog <-> tag
        if (!Schema::hasTable('blog_tag')) {
            Schema::create('blog_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
                $table->foreignId('blog_tag_id')->constrained('tags')->cascadeOnDelete();
                $table->unique(['blog_id', 'blog_tag_id']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tag');
        if (Schema::hasTable('tags')) {
            Schema::rename('tags', 'blog_tags');
        }
    }
};