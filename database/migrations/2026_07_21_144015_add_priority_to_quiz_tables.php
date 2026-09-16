<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Добавляем priority в таблицу результатов, если его там нет
        if (!Schema::hasColumn('quiz_results', 'priority')) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->integer('priority')->default(0)->after('is_active');
            });
        }

        // Добавляем priority в таблицу вопросов, если его там нет (для сортировки вопросов)
        if (!Schema::hasColumn('quiz_questions', 'priority')) {
            Schema::table('quiz_questions', function (Blueprint $table) {
                $table->integer('priority')->default(0)->after('is_active');
            });
        }
    }

    public function down()
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn('priority');
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};