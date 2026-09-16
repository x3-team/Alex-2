<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;

class RecalculateReadingTime extends Command
{
    protected $signature = 'blog:recalculate-time';
    protected $description = 'Пересчитать время чтения для всех статей';

    public function handle()
    {
        $blogs = Blog::all();
        $this->info("Найдено статей: " . $blogs->count());

        foreach ($blogs as $blog) {
            $oldDuration = $blog->duration;

            // Считаем слова с поддержкой кириллицы
            $text = strip_tags($blog->content);
            preg_match_all('/\p{L}+/u', $text, $matches);
            $wordCount = count($matches[0]);

            $minutes = ceil($wordCount / 180);

            if ($minutes <= 1) {
                $newDuration = '1 мин';
            } elseif ($minutes < 60) {
                $newDuration = "{$minutes} мин";
            } else {
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                $newDuration = $mins > 0 ? "{$hours} ч {$mins} мин" : "{$hours} ч";
            }

            $blog->update(['duration' => $newDuration]);

            $this->line("Статья #{$blog->id}: {$oldDuration} → {$newDuration} (слов: {$wordCount})");
        }

        $this->info('✅ Готово!');
    }
}