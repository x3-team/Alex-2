<?php

namespace App\Console\Commands;

use App\Models\Allergen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateAllergens extends Command
{
    /**
     * Имя команды для запуска в консоли
     */
    protected $signature = 'allergens:deduplicate';

    protected $description = 'Убирает названия в скобках и объединяет дубликаты аллергенов';

    public function handle()
    {
        $this->info('Начало обработки аллергенов...');

        DB::transaction(function () {
            $allergens = Allergen::all();

            // Группируем аллергены по очищенному от скобок названию
            $grouped = $allergens->groupBy(function ($item) {
                // Удаляем всё в скобках и лишние пробелы по краям
                $cleanName = preg_replace('/\s*\([^)]*\)/u', '', $item->name);
                return trim($cleanName);
            });

            $totalGroups = $grouped->count();
            $mergedCount = 0;

            foreach ($grouped as $cleanName => $group) {
                // Выбираем первый аллерген в группе как основной
                $mainAllergen = $group->first();

                // Все остальные в группе — дубликаты
                $duplicates = $group->slice(1);

                // Update основного аллергена (чистое название)
                $mainAllergen->name = $cleanName;
                $mainAllergen->save();

                if ($duplicates->isNotEmpty()) {
                    $duplicateIds = $duplicates->pluck('id')->toArray();

                    foreach ($duplicateIds as $dupId) {
                        // Переносим связи, где дубликат был главным аллергеном
                        DB::table('allergen_relations')
                            ->where('allergen_id', $dupId)
                            ->where('related_allergen_id', '!=', $mainAllergen->id)
                            ->update(['allergen_id' => $mainAllergen->id]);

                        // Переносим связи, где дубликат был связанным аллергеном
                        DB::table('allergen_relations')
                            ->where('related_allergen_id', $dupId)
                            ->where('allergen_id', '!=', $mainAllergen->id)
                            ->update(['related_allergen_id' => $mainAllergen->id]);
                    }

                    // Удаляем связи между основным аллергеном и его же дубликатами, если они были
                    DB::table('allergen_relations')
                        ->where('allergen_id', $mainAllergen->id)
                        ->where('related_allergen_id', $mainAllergen->id)
                        ->delete();

                    // Удаляем дубликаты из базы
                    Allergen::whereIn('id', $duplicateIds)->delete();
                    $mergedCount += count($duplicateIds);
                }
            }

            // Удаляем возможные дубликаты связей после переноса
            DB::statement('
                DELETE t1 FROM allergen_relations t1
                INNER JOIN allergen_relations t2 
                WHERE t1.id > t2.id 
                  AND t1.allergen_id = t2.allergen_id 
                  AND t1.related_allergen_id = t2.related_allergen_id
            ');

            $this->info("Обработка завершена!");
            $this->info("Обработано уникальных названий: {$totalGroups}");
            $this->info("Удалено дубликатов: {$mergedCount}");
        });

        return Command::SUCCESS;
    }
}