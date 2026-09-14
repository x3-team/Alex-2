<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('author_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // автор
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // категория
            $table->timestamps();
            
            // Уникальная связь (автор + категория не должны дублироваться)
            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('author_category');
    }
};
