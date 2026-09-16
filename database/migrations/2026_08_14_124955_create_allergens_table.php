<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название (например, "Береза повислая")
            $table->string('category'); // Категория (например, "Пыльца деревьев")
            $table->string('code')->nullable(); // Обозначение (Bet v 1, Cyn d и т.д.)
            $table->string('protein_family')->nullable(); // Семейство белков (PR-10, Beta-Expansin)
            $table->enum('type', ['E', 'M'])->default('E'); // E = экстракт, M = молекулярный
            $table->text('description')->nullable(); // Описание
            $table->boolean('included')->default(true); // Входит ли в тест ALEX2
            $table->timestamps();
        });

        // Таблица связей аллергенов между собой (Self-referencing Many-to-Many)
        Schema::create('allergen_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allergen_id')->constrained('allergens')->onDelete('cascade');
            $table->foreignId('related_allergen_id')->constrained('allergens')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['allergen_id', 'related_allergen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergen_relations');
        Schema::dropIfExists('allergens');
    }
};