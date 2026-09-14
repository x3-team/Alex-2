<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Удаляем старую таблицу, если она была создана ранее
        Schema::dropIfExists('quiz_conditions');

        // Создаем новую таблицу правил (сценариев)
        Schema::create('quiz_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('quiz_results')->cascadeOnDelete();
            $table->integer('priority')->default(1); // 1 = наивысший приоритет (проверяется первым)
            $table->json('conditions'); // Массив условий: [{"question_id": 1, "answer_ids": [2, 3]}]
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_rules');
    }
};