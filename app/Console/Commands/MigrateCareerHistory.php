<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class MigrateCareerHistory extends Command
{
    protected $signature = 'migrate:career-history';
    protected $description = 'Миграция career_history: старая структура {date, place} -> {year_from, year_to, place}';

    public function handle()
    {
        $currentYear = date('Y');
        $updated = 0;

        User::whereNotNull('career_history')->each(function ($author) use ($currentYear, &$updated) {
            $careerData = json_decode($author->career_history, true);

            if (!is_array($careerData)) {
                $this->warn("⚠️ Автор ID {$author->id}: невалидный JSON");
                return;
            }

            $changed = false;
            $newCareerData = [];

            foreach ($careerData as $entry) {
                $newEntry = [];

                // 🔹 🔥 Миграция старой структуры {date, place} -> {year_from, year_to, place}
                if (isset($entry['date']) && !isset($entry['year_from'])) {
                    $date = $entry['date'];

                    // Парсим год начала из строки "2020 — н.в." или "2020"
                    if (preg_match('/(\d{4})/', $date, $matches)) {
                        $newEntry['year_from'] = (int)$matches[1];
                    } else {
                        $newEntry['year_from'] = null;
                    }

                    // Парсим год окончания
                    if (preg_match('/—\s*(\d{4})/', $date, $matches)) {
                        $newEntry['year_to'] = (int)$matches[1];
                    } else {
                        // "н.в." или нет года окончания — оставляем null (работает до сих пор)
                        $newEntry['year_to'] = null;
                    }

                    $newEntry['place'] = $entry['place'] ?? '';
                    $changed = true;
                }
                // 🔹 🔥 Если уже новая структура — оставляем как есть
                elseif (isset($entry['year_from'])) {
                    $newEntry = $entry;
                }

                if (!empty($newEntry['year_from']) || !empty($newEntry['place'])) {
                    $newCareerData[] = $newEntry;
                }
            }

            if ($changed || count($newCareerData) !== count($careerData)) {
                $author->career_history = json_encode($newCareerData, JSON_UNESCAPED_UNICODE);
                $author->save();
                $updated++;
                $this->info("✅ Автор ID {$author->id}: мигрировано");
            }
        });

        $this->info("✅ Обновлено {$updated} авторов");
    }
}