<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Картотека лабораторного заказа отдельно от заявок orders: медполя шифруются в моделях.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_patients', function (Blueprint $table) {
            $table->id();
            $table->string('medmis_patient_id', 36)->nullable()->unique();
            $table->text('last_name');
            $table->text('first_name');
            $table->text('middle_name')->nullable();
            $table->text('birth_date');
            $table->text('sex');
            $table->text('phone');
            $table->text('email')->nullable();
            $table->char('phone_lookup', 64)->index();
            $table->char('identity_lookup', 64)->index();
            // Карточка из скрытого доступа: в Medmis уходит с пометкой TEST, с боевыми не смешивается.
            $table->boolean('is_test')->default(false);
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_patient_id')->constrained('lab_patients')->cascadeOnDelete();
            $table->foreignId('site_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('submission_id', 64)->nullable()->unique();
            $table->string('payment_mode', 16);
            $table->string('payment_status', 32);
            $table->string('yookassa_payment_id')->nullable();
            $table->text('yookassa_confirmation_url')->nullable();
            $table->date('visit_date');
            $table->string('visit_time', 5);
            $table->string('status', 32)->default('created');
            $table->string('failure_reason', 64)->nullable();
            $table->string('medmis_appointment_id', 36)->nullable();
            $table->unsignedBigInteger('medmis_invoice_id')->nullable();
            $table->string('medmis_status', 32)->nullable();
            $table->string('dnkom_nr', 64)->nullable();
            $table->string('dnkom_sender', 64)->nullable();
            $table->timestamp('medmis_dnkom_reported_at')->nullable();
            $table->timestamp('consent_at');
            $table->string('consent_text_version', 32);
            $table->boolean('is_test')->default(false);
            $table->timestamps();
        });

        Schema::create('lab_patient_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_patient_id')->constrained('lab_patients')->cascadeOnDelete();
            $table->foreignId('lab_order_id')->nullable()->constrained('lab_orders')->nullOnDelete();
            $table->string('actor', 64);
            $table->string('action', 32);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('lab_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('title');
            $table->string('dnkom_code')->nullable();
            $table->string('medmis_service_id', 36)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('lab_result_blanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('blank_id');
            $table->string('blank_guid');
            $table->string('storage_path')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['lab_order_id', 'blank_id', 'blank_guid']);
        });

        Schema::create('lab_dictionary_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->longText('payload');
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('lab_outbound_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('step', 32);
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('reason', 64)->nullable();
            $table->timestamps();
            $table->unique(['lab_order_id', 'step']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_outbound_jobs');
        Schema::dropIfExists('lab_dictionary_snapshots');
        Schema::dropIfExists('lab_result_blanks');
        Schema::dropIfExists('lab_order_lines');
        Schema::dropIfExists('lab_patient_access_logs');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_patients');
    }
};
