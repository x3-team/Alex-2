<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->onDelete('cascade');
            $table->foreignId('related_blog_id')->constrained('blogs')->onDelete('cascade');
            $table->unique(['blog_id', 'related_blog_id']); // чтобы не было дублей
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_related');
    }
};