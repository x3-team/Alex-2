<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('home_settings', function (Blueprint $table) {
            $table->id();

            $table->json('advantages')->nullable();

            $table->json('results')->nullable();

            $table->json('faq')->nullable();

            $table->json('featured_blog_ids')->nullable();
            $table->text('how_to_test_text')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('home_settings');
    }
};