<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_url',
        'to_url',
        'status_code',
        'is_active',
        'hits_count',
        'last_hit_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_code' => 'integer',
        'hits_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    // Нормализация URL (убираем протокол, домен, слеши)
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Убираем протокол и домен
        $url = preg_replace('#^https?://[^/]+#', '', $url);

        // Убираем query string
        $url = strtok($url, '?');

        // Убираем якорь
        $url = strtok($url, '#');

        // Добавляем начальный слеш
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        // Убираем дубли слешей
        $url = preg_replace('#/+#', '/', $url);

        // Убираем конечный слеш (кроме главной)
        if ($url !== '/' && str_ends_with($url, '/')) {
            $url = rtrim($url, '/');
        }

        return strtolower($url);
    }

    // Поиск редиректа по URL
    public static function flattenToUrl(string $toUrl, ?string $fromUrl = null): string
    {
        $to = self::normalizeUrl($toUrl);
        $seen = [];
        if ($fromUrl) {
            $seen[self::normalizeUrl($fromUrl)] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            if (isset($seen[$to])) {
                break;
            }
            $seen[$to] = true;
            $next = self::where('from_url', $to)->where('is_active', true)->first();
            if (!$next) {
                break;
            }
            $candidate = self::normalizeUrl((string) $next->to_url);
            if ($candidate === '' || $candidate === $to) {
                break;
            }
            $to = $candidate;
        }
        return $to;
    }

    public static function findByUrl(string $url): ?self
    {
        $normalized = self::normalizeUrl($url);

        return self::where('from_url', $normalized)
            ->where('is_active', true)
            ->first();
    }

    // Инкремент счётчика срабатываний
    public function incrementHits(): void
    {
        $this->increment('hits_count');
        $this->update(['last_hit_at' => now()]);
    }
}