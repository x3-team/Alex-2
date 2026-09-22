<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

class DoctorMaterialsStore
{
    public const FILES_KEY = 'doctor_materials';

    public const CATEGORIES_KEY = 'doctor_material_categories';

    public const MAX_CATEGORIES = 6;

    public function categories(): array
    {
        return array_values(array_filter(array_map(function ($row) {
            if (! is_array($row)) {
                return null;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                return null;
            }

            return [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'name' => $name,
                'slug' => $this->slug((string) ($row['slug'] ?? ''), $name),
                'description' => trim((string) ($row['description'] ?? '')),
                'link_url' => self::normalizeCategoryLink((string) ($row['link_url'] ?? '')) ?? '',
            ];
        }, $this->decode(self::CATEGORIES_KEY))));
    }

    public function files(): array
    {
        $i = 0;

        $files = array_values(array_filter(array_map(function ($row) use (&$i) {
            if (! is_array($row)) {
                return null;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                return null;
            }

            $i++;

            return [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'title' => $title,
                'file_path' => trim((string) ($row['file_path'] ?? '')),
                'link_url' => trim((string) ($row['link_url'] ?? '')),
                'date' => trim((string) ($row['date'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'category_id' => trim((string) ($row['category_id'] ?? '')),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $i,
            ];
        }, $this->decode(self::FILES_KEY))));

        return self::sortFiles($files);
    }

    public function publicCategories(): array
    {
        return array_slice($this->categories(), 0, self::MAX_CATEGORIES);
    }

    public function categoryBySlug(string $slug): ?array
    {
        foreach ($this->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }

    public function filesForCategory(string $categoryId): array
    {
        return array_values(array_filter(
            $this->files(),
            fn ($file) => ($file['category_id'] ?? '') === $categoryId
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @return list<array<string, mixed>>
     */
    public static function sortFiles(array $files): array
    {
        $indexed = array_values($files);
        usort($indexed, function ($a, $b) {
            $byCategory = strcmp((string) ($a['category_id'] ?? ''), (string) ($b['category_id'] ?? ''));
            if ($byCategory !== 0) {
                return $byCategory;
            }

            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });

        return $indexed;
    }

    /**
     * @return list<array{id:string,name:string,slug:string,description:string,link_url:string,count:int,count_label:string}>
     */
    public function categoriesWithCounts(): array
    {
        $files = $this->files();

        return array_map(function (array $category) use ($files) {
            $count = count(array_filter($files, fn ($file) => ($file['category_id'] ?? '') === $category['id']));

            return [...$category, 'count' => $count, 'count_label' => self::ruDocuments($count)];
        }, $this->categories());
    }

    public function ensureDefaultCategory(): array
    {
        $categories = $this->categories();
        $files = $this->files();

        if ($categories !== []) {
            return $categories;
        }

        if ($files === []) {
            return [];
        }

        $default = [
            'id' => (string) Str::uuid(),
            'name' => 'Документы',
            'slug' => 'dokumenty',
            'description' => 'Регистрационные документы, инструкции и бланки лаборатории.',
            'link_url' => '',
        ];

        $files = array_map(function (array $file) use ($default) {
            if (($file['category_id'] ?? '') === '') {
                $file['category_id'] = $default['id'];
            }

            return $file;
        }, $files);

        $this->save([$default], $files);

        return [$default];
    }

    public function save(array $categories, array $files): void
    {
        Setting::set(self::CATEGORIES_KEY, json_encode(array_values($categories), JSON_UNESCAPED_UNICODE));
        Setting::set(self::FILES_KEY, json_encode(array_values($files), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Empty stays empty. A leading slash is an in-site path.
     * Anything else must be an http(s) URL (https:// is added if the scheme is missing).
     * Invalid input returns null.
     */
    public static function normalizeCategoryLink(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public static function ruDocuments(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $n.' документов';
        }

        if ($mod10 === 1) {
            return $n.' документ';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $n.' документа';
        }

        return $n.' документов';
    }

    private function decode(string $key): array
    {
        $raw = Setting::get($key, []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    private function slug(string $slug, string $fallback): string
    {
        $slug = Str::slug($slug !== '' ? $slug : $fallback, '-', 'ru');

        return $slug !== '' ? $slug : 'dokumenty';
    }
}
