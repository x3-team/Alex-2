<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Таблица результатов квиза
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Заголовок результата
            $table->text('description'); // Описание
            $table->string('primary_button_text')->nullable();
            $table->string('primary_button_url')->nullable();
            $table->string('secondary_button_text')->nullable();
            $table->string('secondary_button_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Таблица вопросов
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->default(0);
            $table->text('question_text');
            $table->enum('audience', ['all', 'adult', 'child'])->default('all');
            $table->enum('question_type', ['single', 'multiple'])->default('single'); // single/multiple
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Таблица ответов
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->onDelete('cascade');
            $table->text('answer_text'); // Текст ответа
            $table->integer('order')->default(0); // Порядок ответа
            $table->foreignId('next_question_id')->nullable()->constrained('quiz_questions')->onDelete('set null'); // Следующий вопрос
            $table->foreignId('result_id')->nullable()->constrained('quiz_results')->onDelete('set null'); // Результат (если это конец)
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quiz_results');
    }
};