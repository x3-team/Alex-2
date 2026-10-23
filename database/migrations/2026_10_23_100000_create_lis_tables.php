<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Интеграция с ЛИС «АльфаЛАБ», каркас: журнал обмена (без ПДн и результатов),
// кэш номенклатуры, состояние синхронизации и хранилище эмулятора ЛИС.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lis_exchange_log', function (Blueprint $table) {
            $table->id();
            $table->string('message_type', 64);
            $table->string('outcome', 24); // ok | warning | empty | business_error | auth_error | ...
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('mis_id', 64)->nullable();
            $table->unsignedBigInteger('lis_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['mis_id']);
            $table->index(['lis_id']);
            $table->index(['outcome', 'created_at']);
        });

        Schema::create('lab_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('kind', 16); // analysis | panel
            $table->unsignedBigInteger('lis_id')->nullable();
            $table->string('name');
            $table->json('biomaterial_codes')->nullable();
            $table->json('required_fields')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('min_duration')->nullable();
            $table->unsignedSmallInteger('max_duration')->nullable();
            $table->string('duration_unit', 8)->nullable(); // hours | days
            $table->date('suspended_from')->nullable();
            $table->date('suspended_till')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->boolean('removed')->default(false);
            $table->unsignedInteger('dictionary_version')->nullable();
            $table->timestamps();
        });

        Schema::create('lis_sync_state', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Только для ALFALAB_DRIVER=fake: направления «лаборатории-эмулятора». Без ПДн пациента.
        Schema::create('lis_fake_referrals', function (Blueprint $table) {
            $table->id(); // = LisId
            $table->string('nr', 32)->unique();
            $table->string('mis_id', 64)->nullable()->unique();
            $table->json('orders');
            $table->string('stage', 16)->default('created'); // created | activated | done | removed
            $table->unsignedInteger('version')->default(1);
            $table->boolean('queued')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lis_fake_referrals');
        Schema::dropIfExists('lis_sync_state');
        Schema::dropIfExists('lab_services');
        Schema::dropIfExists('lis_exchange_log');
    }
};
