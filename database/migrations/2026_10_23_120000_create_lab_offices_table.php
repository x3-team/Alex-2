<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Офисы лаборатории, где можно сдать кровь на ALEX2. Список даёт лаборатория,
// вводится в админке вручную или импортом CSV. Офис не удаляется, а выключается.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_offices', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->string('name');
            $table->string('address');
            $table->string('metro')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('hours_weekdays', 100)->nullable();
            $table->string('hours_weekend', 100)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('lis_code', 64)->nullable()->unique(); // код офиса у лаборатории, если дадут
            $table->boolean('accepts_alex2')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('badge', 100)->nullable(); // плашка акции в списке
            $table->text('note')->nullable(); // подсказка пациенту: вход, этаж, подготовка
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'accepts_alex2', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_offices');
    }
};
