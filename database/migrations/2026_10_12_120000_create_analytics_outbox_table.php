<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ТЗ по аналитике, этап А: повтор отправки формы не создаёт вторую заявку (submission_id),
// принятая заявка фиксируется в журнале доставки по одной строке на платформу.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('submission_id', 64)->nullable()->unique()->after('id');
        });

        Schema::create('analytics_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('event_name', 40);
            $table->string('platform', 16); // ga4 | metrika
            $table->string('inquiry_ref', 64); // site_order:<id>
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('params');
            $table->string('client_id', 64)->nullable();
            $table->string('consent', 16)->nullable();
            $table->string('status', 16)->default('pending'); // pending | sent | suppressed | failed
            $table->string('reason', 32)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'platform']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_outbox');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['submission_id']);
            $table->dropColumn('submission_id');
        });
    }
};
