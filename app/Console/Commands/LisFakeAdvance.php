<?php

namespace App\Console\Commands;

use App\Lis\AlfaLab\Transport\FakeTransport;
use App\Models\Lis\LisFakeReferral;
use Illuminate\Console\Command;

class LisFakeAdvance extends Command
{
    protected $signature = 'lis:fake-advance {ref : LisId, номер направления или MisId}';

    protected $description = 'Эмулятор ЛИС: двигает направление на шаг (кровь взята → результат готов)';

    public function handle(): int
    {
        if (config('alfalab.driver') !== 'fake') {
            $this->error('Команда работает только с эмулятором (ALFALAB_DRIVER=fake).');

            return self::FAILURE;
        }

        $ref = (string) $this->argument('ref');
        $row = LisFakeReferral::query()
            ->where('nr', $ref)
            ->orWhere('mis_id', $ref)
            ->when(ctype_digit($ref), fn ($q) => $q->orWhere('id', (int) $ref))
            ->first();

        if (! $row) {
            $this->error("Направление {$ref} не найдено в эмуляторе.");

            return self::FAILURE;
        }

        $row = FakeTransport::advance($row);
        $this->info("Направление {$row->nr}: {$row->stage}, версия {$row->version}");

        return self::SUCCESS;
    }
}
