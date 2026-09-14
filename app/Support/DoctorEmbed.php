<?php

namespace App\Support;

class DoctorEmbed
{
    public static function source(?string $url): string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        if (str_contains($host, 'youtu')) {
            return 'youtube';
        }

        if (str_contains($host, 'vk.com') || str_contains($host, 'vkvideo')) {
            return 'vk';
        }

        return 'other';
    }

    public static function sourceLabel(?string $source, ?string $url = null): string
    {
        $resolved = $source ?: self::source($url);

        return match ($resolved) {
            'youtube' => 'YouTube',
            'vk' => 'VK Видео',
            default => 'Видео',
        };
    }

    public static function iframeSrc(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('~(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~(?:youtube\.com/watch\?.*v=)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~video_ext\.php\?([^"\s]+)~', $url, $m)) {
            parse_str(html_entity_decode($m[1]), $params);
            $oid = $params['oid'] ?? null;
            $id = $params['id'] ?? null;
            if ($oid && $id) {
                return 'https://vk.com/video_ext.php?oid='.rawurlencode((string) $oid).'&id='.rawurlencode((string) $id).'&hd=2';
            }
        }

        if (preg_match('~video(-?\d+)_(\d+)~', $url, $m)) {
            return 'https://vk.com/video_ext.php?oid='.rawurlencode($m[1]).'&id='.rawurlencode($m[2]).'&hd=2';
        }

        return null;
    }
}
