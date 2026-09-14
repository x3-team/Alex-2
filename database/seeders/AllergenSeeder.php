<?php

namespace Database\Seeders;

use App\Models\Allergen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AllergenSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Allergen::truncate();
        DB::table('allergen_relations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Массив всех данных из PDF ALEX2 и стартового Vue-компонента
        $allergens = [
            // Пыльца трав
            ['name' => 'Свинорой пальчатый', 'category' => 'Пыльца трав', 'code' => 'Cyn d', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Свинорой пальчатый (Cyn d 1)', 'category' => 'Пыльца трав', 'code' => 'Cyn d 1', 'type' => 'M', 'protein_family' => 'Beta-Expansin'],
            ['name' => 'Плевел многолетний (Lol p 1)', 'category' => 'Пыльца трав', 'code' => 'Lol p 1', 'type' => 'M', 'protein_family' => 'Beta-Expansin'],
            ['name' => 'Паспалум/гречка заметная', 'category' => 'Пыльца трав', 'code' => 'Pas n', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Тимофеевка луговая (Phl p 1)', 'category' => 'Пыльца трав', 'code' => 'Phl p 1', 'type' => 'M', 'protein_family' => 'Beta-Expansin'],
            ['name' => 'Тимофеевка луговая (Phl p 2)', 'category' => 'Пыльца трав', 'code' => 'Phl p 2', 'type' => 'M', 'protein_family' => 'Expansin'],
            ['name' => 'Тимофеевка луговая (Phl p 5.0101)', 'category' => 'Пыльца трав', 'code' => 'Phl p 5.0101', 'type' => 'M', 'protein_family' => 'Grass Group 5/6'],
            ['name' => 'Тимофеевка луговая (Phl p 6)', 'category' => 'Пыльца трав', 'code' => 'Phl p 6', 'type' => 'M', 'protein_family' => 'Grass Group 5/6'],
            ['name' => 'Тимофеевка луговая (Phl p 7)', 'category' => 'Пыльца трав', 'code' => 'Phl p 7', 'type' => 'M', 'protein_family' => 'Polcalcin'],
            ['name' => 'Тимофеевка луговая (Phl p 12)', 'category' => 'Пыльца трав', 'code' => 'Phl p 12', 'type' => 'M', 'protein_family' => 'Profilin'],
            ['name' => 'Тростник', 'category' => 'Пыльца трав', 'code' => 'Phr c', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Рожь посевная (пыльца)', 'category' => 'Пыльца трав', 'code' => 'Sec c_pollen', 'type' => 'E', 'protein_family' => null],

            // Пыльца деревьев
            ['name' => 'Акация серебристая', 'category' => 'Пыльца деревьев', 'code' => 'Aca m', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Айлант высочайший', 'category' => 'Пыльца деревьев', 'code' => 'Ail a', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Ольха (Aln g 1)', 'category' => 'Пыльца деревьев', 'code' => 'Aln g 1', 'type' => 'M', 'protein_family' => 'PR-10'],
            ['name' => 'Ольха (Aln g 4)', 'category' => 'Пыльца деревьев', 'code' => 'Aln g 4', 'type' => 'M', 'protein_family' => 'Polcalcin'],
            ['name' => 'Берёза повислая (Bet v 1)', 'category' => 'Пыльца деревьев', 'code' => 'Bet v 1', 'type' => 'M', 'protein_family' => 'PR-10'],
            ['name' => 'Берёза повислая (Bet v 2)', 'category' => 'Пыльца деревьев', 'code' => 'Bet v 2', 'type' => 'M', 'protein_family' => 'Profilin'],
            ['name' => 'Берёза повислая (Bet v 6)', 'category' => 'Пыльца деревьев', 'code' => 'Bet v 6', 'type' => 'M', 'protein_family' => 'Isoflavon Reductase'],
            ['name' => 'Бумажная шелковица', 'category' => 'Пыльца деревьев', 'code' => 'Bro pa', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Орешник (Лещина)', 'category' => 'Пыльца деревьев', 'code' => 'Cor a_pollen', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Орешник (Cor a 1.0103)', 'category' => 'Пыльца деревьев', 'code' => 'Cor a 1.0103', 'type' => 'M', 'protein_family' => 'PR-10'],
            ['name' => 'Криптомерия японская', 'category' => 'Пыльца деревьев', 'code' => 'Cry j 1', 'type' => 'M', 'protein_family' => 'Pectate Lyase'],
            ['name' => 'Кипарис (Cup a 1)', 'category' => 'Пыльца деревьев', 'code' => 'Cup a 1', 'type' => 'M', 'protein_family' => 'Pectate Lyase'],
            ['name' => 'Кипарис (Cup s)', 'category' => 'Пыльца деревьев', 'code' => 'Cup s', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Бук (Fag s 1)', 'category' => 'Пыльца деревьев', 'code' => 'Fag s 1', 'type' => 'M', 'protein_family' => 'PR-10'],
            ['name' => 'Ясень', 'category' => 'Пыльца деревьев', 'code' => 'Fra e', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Ясень (Fra e 1)', 'category' => 'Пыльца деревьев', 'code' => 'Fra e 1', 'type' => 'M', 'protein_family' => 'Ole e 1-Family'],
            ['name' => 'Грецкий орех (пыльца)', 'category' => 'Пыльца деревьев', 'code' => 'Jug r_pollen', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Кедр', 'category' => 'Пыльца деревьев', 'code' => 'Jun a', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Шелковица', 'category' => 'Пыльца деревьев', 'code' => 'Mor r', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Олива (Ole e 1)', 'category' => 'Пыльца деревьев', 'code' => 'Ole e 1', 'type' => 'M', 'protein_family' => 'Ole e 1-Family'],
            ['name' => 'Олива (Ole e 9)', 'category' => 'Пыльца деревьев', 'code' => 'Ole e 9', 'type' => 'M', 'protein_family' => '1,3 β Glucanase'],
            ['name' => 'Финиковая пальма', 'category' => 'Пыльца деревьев', 'code' => 'Pho d 2', 'type' => 'M', 'protein_family' => 'Profilin'],
            ['name' => 'Платан кленолистный (Pla a 1)', 'category' => 'Пыльца деревьев', 'code' => 'Pla a 1', 'type' => 'M', 'protein_family' => 'Plant Invertase'],
            ['name' => 'Платан кленолистный (Pla a 2)', 'category' => 'Пыльца деревьев', 'code' => 'Pla a 2', 'type' => 'M', 'protein_family' => 'Polygalacturonase'],
            ['name' => 'Платан кленолистный (Pla a 3)', 'category' => 'Пыльца деревьев', 'code' => 'Pla a 3', 'type' => 'M', 'protein_family' => 'nsLTP'],
            ['name' => 'Тополь', 'category' => 'Пыльца деревьев', 'code' => 'Pop n', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Вяз', 'category' => 'Пыльца деревьев', 'code' => 'Ulm c', 'type' => 'E', 'protein_family' => null],

            // Пыльца сорных трав
            ['name' => 'Обыкновенная марь', 'category' => 'Пыльца сорных трав', 'code' => 'Ama r', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Амброзия (Amb a)', 'category' => 'Пыльца сорных трав', 'code' => 'Amb a', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Амброзия (Amb a 1)', 'category' => 'Пыльца сорных трав', 'code' => 'Amb a 1', 'type' => 'M', 'protein_family' => 'Pectate Lyase'],
            ['name' => 'Амброзия (Amb a 4)', 'category' => 'Пыльца сорных трав', 'code' => 'Amb a 4', 'type' => 'M', 'protein_family' => 'Plant Defensin'],
            ['name' => 'Полынь (Art v)', 'category' => 'Пыльца сорных трав', 'code' => 'Art v', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Полынь (Art v 1)', 'category' => 'Пыльца сорных трав', 'code' => 'Art v 1', 'type' => 'M', 'protein_family' => 'Plant Defensin'],
            ['name' => 'Полынь (Art v 3)', 'category' => 'Пыльца сорных трав', 'code' => 'Art v 3', 'type' => 'M', 'protein_family' => 'nsLTP'],
            ['name' => 'Конопля (Can s)', 'category' => 'Пыльца сорных трав', 'code' => 'Can s', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Конопля (Can s 3)', 'category' => 'Пыльца сорных трав', 'code' => 'Can s 3', 'type' => 'M', 'protein_family' => 'nsLTP'],
            ['name' => 'Марь белая (Che a)', 'category' => 'Пыльца сорных трав', 'code' => 'Che a', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Марь белая (Che a 1)', 'category' => 'Пыльца сорных трав', 'code' => 'Che a 1', 'type' => 'M', 'protein_family' => 'Ole e 1-Family'],
            ['name' => 'Пролесник однолетний', 'category' => 'Пыльца сорных трав', 'code' => 'Mer a 1', 'type' => 'M', 'protein_family' => 'Profilin'],
            ['name' => 'Постенница (Par j)', 'category' => 'Пыльца сорных трав', 'code' => 'Par j', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Постенница (Par j 2)', 'category' => 'Пыльца сорных трав', 'code' => 'Par j 2', 'type' => 'M', 'protein_family' => 'nsLTP'],
            ['name' => 'Подорожник (Pla l)', 'category' => 'Пыльца сорных трав', 'code' => 'Pla l', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Подорожник (Pla l 1)', 'category' => 'Пыльца сорных трав', 'code' => 'Pla l 1', 'type' => 'M', 'protein_family' => 'Ole e 1-Family'],
            ['name' => 'Солянка (Sal k)', 'category' => 'Пыльца сорных трав', 'code' => 'Sal k', 'type' => 'E', 'protein_family' => null],
            ['name' => 'Солянка (Sal k 1)', 'category' => 'Пыльца сорных трав', 'code' => 'Sal k 1', 'type' => 'M', 'protein_family' => 'Pectin Methylesterase'],
            ['name' => 'Крапива', 'category' => 'Пыльца сорных трав', 'code' => 'Urt d', 'type' => 'E', 'protein_family' => null],

            // Клещи и тараканы
            ['name' => 'Американский клещ домашней пыли (Der f 1)', 'category' => 'Клещи и тараканы', 'code' => 'Der f 1', 'type' => 'M', 'protein_family' => 'Cysteine protease'],
            ['name' => 'Американский клещ домашней пыли (Der f 2)', 'category' => 'Клещи и тараканы', 'code' => 'Der f 2', 'type' => 'M', 'protein_family' => 'NPC2 Family'],
            ['name' => 'Европейский клещ домашней пыли (Der p 1)', 'category' => 'Клещи и тараканы', 'code' => 'Der p 1', 'type' => 'M', 'protein_family' => 'Cysteine protease'],
            ['name' => 'Европейский клещ домашней пыли (Der p 2)', 'category' => 'Клещи и тараканы', 'code' => 'Der p 2', 'type' => 'M', 'protein_family' => 'NPC2 Family'],
            ['name' => 'Европейский клещ домашней пыли (Der p 10)', 'category' => 'Клещи и тараканы', 'code' => 'Der p 10', 'type' => 'M', 'protein_family' => 'Tropomyosin'],
            ['name' => 'Рыжий (Немецкий) таракан (Bla g 1)', 'category' => 'Клещи и тараканы', 'code' => 'Bla g 1', 'type' => 'M', 'protein_family' => 'Cockroach Group 1'],
            ['name' => 'Американский таракан', 'category' => 'Клещи и тараканы', 'code' => 'Per a', 'type' => 'E', 'protein_family' => null],

            // Эпидермис и шерсть животных
            ['name' => 'Собака (Can f 1)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Can f 1', 'type' => 'M', 'protein_family' => 'Lipocalin'],
            ['name' => 'Собака (Can f 2)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Can f 2', 'type' => 'M', 'protein_family' => 'Lipocalin'],
            ['name' => 'Кот (Fel d 1)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Fel d 1', 'type' => 'M', 'protein_family' => 'Uteroglobin'],
            ['name' => 'Кот (Fel d 2)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Fel d 2', 'type' => 'M', 'protein_family' => 'Serum Albumin'],
            ['name' => 'Лошадь (Equ c 1)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Equ c 1', 'type' => 'M', 'protein_family' => 'Lipocalin'],
            ['name' => 'Корова (Bos d 2)', 'category' => 'Эпидермис и шерсть животных', 'code' => 'Bos d 2', 'type' => 'M', 'protein_family' => 'Lipocalin'],

            // Продукты
            ['name' => 'Пшеница (Tri a 19)', 'category' => 'Продукты', 'code' => 'Tri a 19', 'type' => 'M', 'protein_family' => 'Omega-5-Gliadin'],
            ['name' => 'Яблоко (Mal d 1)', 'category' => 'Продукты', 'code' => 'Mal d 1', 'type' => 'M', 'protein_family' => 'PR-10'],
            ['name' => 'Персик (Pru p 3)', 'category' => 'Продукты', 'code' => 'Pru p 3', 'type' => 'M', 'protein_family' => 'nsLTP'],
            ['name' => 'Арахис (Ara h 1)', 'category' => 'Продукты', 'code' => 'Ara h 1', 'type' => 'M', 'protein_family' => '7/8S Globulin'],
            ['name' => 'Коровье молоко (Bos d 4)', 'category' => 'Продукты', 'code' => 'Bos d 4', 'type' => 'M', 'protein_family' => 'α-Lactalbumin'],
            ['name' => 'Яичный белок (Gal d 1)', 'category' => 'Продукты', 'code' => 'Gal d 1', 'type' => 'M', 'protein_family' => 'Ovomucoid'],

            // Другие
            ['name' => 'Латекс (Hev b 1)', 'category' => 'Другие', 'code' => 'Hev b 1', 'type' => 'M', 'protein_family' => 'Rubber elongation factor'],
            ['name' => 'Латекс (Hev b 8)', 'category' => 'Другие', 'code' => 'Hev b 8', 'type' => 'M', 'protein_family' => 'Profilin'],
        ];

        $createdModels = [];

        foreach ($allergens as $item) {
            $createdModels[$item['name']] = Allergen::create([
                'name' => $item['name'],
                'category' => $item['category'],
                'code' => $item['code'],
                'type' => $item['type'],
                'protein_family' => $item['protein_family'],
                'description' => trim(($item['code'] ? $item['code'] . ' ' : '') . ($item['protein_family'] ? '(' . $item['protein_family'] . ')' : '')),
                'included' => true
            ]);
        }

        // Автоматическая установка перекрестных связей по семейству или типу
        $birch = $createdModels['Берёза повислая (Bet v 1)'] ?? null;
        $alder = $createdModels['Ольха (Aln g 1)'] ?? null;
        $apple = $createdModels['Яблоко (Mal d 1)'] ?? null;
        $hazel = $createdModels['Орешник (Cor a 1.0103)'] ?? null;

        if ($birch && $alder && $apple && $hazel) {
            $birch->relatedAllergens()->sync([$alder->id, $apple->id, $hazel->id]);
            $alder->relatedAllergens()->sync([$birch->id, $apple->id]);
            $apple->relatedAllergens()->sync([$birch->id, $alder->id]);
        }
    }
}