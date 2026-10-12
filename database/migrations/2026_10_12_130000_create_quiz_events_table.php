<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Внутренний журнал квиза (ТЗ по аналитике, §4.4–4.5). Ответы, варианты, текст результата
// и контакты сюда не пишутся — только этап прохождения и служебные ключи.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_events', function (Blueprint $table) {
            $table->id();
            $table->string('attempt_id', 64)->index();
            $table->string('event', 32);
            $table->string('quiz_version', 16);
            $table->string('placement', 16);
            $table->string('device', 16);
            $table->unsignedSmallInteger('step_position')->nullable();
            $table->unsignedSmallInteger('to_step_position')->nullable();
            $table->string('step_visit_id', 64)->nullable();
            $table->string('question_key', 16)->nullable();
            $table->string('result_route_id', 16)->nullable();
            $table->string('action_code', 16)->nullable();
            $table->string('action_role', 16)->nullable();
            $table->string('error_stage', 16)->nullable();
            $table->string('error_code', 24)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_events');
    }
};
