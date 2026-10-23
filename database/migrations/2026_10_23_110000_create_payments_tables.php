<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Онлайн-оплата ЮKassa: платежи, возвраты и журнал уведомлений (без тел уведомлений).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Idempotence-Key создания платежа
            $table->nullableMorphs('payable');
            $table->string('provider', 16);
            $table->string('provider_payment_id', 64)->nullable()->unique();
            $table->string('status', 24)->default('new'); // new | pending | waiting_for_capture | succeeded | canceled
            $table->unsignedBigInteger('amount_kopecks');
            $table->unsignedBigInteger('refunded_kopecks')->default(0);
            $table->string('description', 128);
            $table->json('receipt_items');
            $table->text('receipt_contact'); // e-mail/телефон для чеков, шифруется (encrypted:array)
            $table->text('confirmation_url')->nullable();
            $table->boolean('test')->default(false);
            $table->string('cancellation_reason', 64)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('settlement_receipt_id', 64)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments');
            $table->string('provider_refund_id', 64)->nullable()->unique();
            $table->string('status', 24)->default('new'); // new | pending | succeeded | canceled
            $table->unsignedBigInteger('amount_kopecks');
            $table->string('reason', 250);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->string('event', 48)->nullable();
            $table->string('object_id', 64)->nullable();
            $table->string('outcome', 24); // processed | unknown_object | rejected_ip | disabled | bad_request | error
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_log');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
    }
};
