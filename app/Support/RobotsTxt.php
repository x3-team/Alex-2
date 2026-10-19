<?php

namespace App\Support;

/**
 * Текст robots.txt из админки (или дефолт) плюс обязательные строки, которые код
 * добавляет сам на обоих доменах: Disallow для /search и /cart в каждой группе
 * User-agent и Sitemap этого домена. Всё остальное из админки остаётся как есть.
 */
class RobotsTxt
{
    public const REQUIRED_DISALLOW = ['/search', '/cart'];

    public static function finalize(string $content, string $sitemapUrl): string
    {
        $content = str_replace('{{sitemap_url}}', $sitemapUrl, $content);
        $lines = preg_split('/\r\n|\r|\n/', $content);

        // Строки Sitemap: заменяем одной правильной в конце: на doc-домене в тексте
        // из админки стояла карта пациентского сайта.
        $lines = array_values(array_filter(
            $lines,
            fn ($line) => ! preg_match('/^\s*sitemap\s*:/i', $line)
        ));

        $out = [];
        $group = null;
        $flush = function () use (&$out, &$group) {
            if ($group === null) {
                return;
            }
            $insertAt = $group['lastRule'] ?? $group['lastAgent'];
            $missing = [];
            foreach (self::REQUIRED_DISALLOW as $path) {
                if (! in_array($path, $group['disallow'], true)) {
                    $missing[] = 'Disallow: '.$path;
                }
            }
            if ($missing) {
                array_splice($out, $insertAt + 1, 0, $missing);
            }
            $group = null;
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^user-agent\s*:/i', $trim)) {
                if ($group !== null && $group['lastRule'] !== null) {
                    $flush();
                }
                $out[] = $line;
                $idx = count($out) - 1;
                if ($group === null) {
                    $group = ['lastAgent' => $idx, 'lastRule' => null, 'disallow' => []];
                } else {
                    $group['lastAgent'] = $idx;
                }

                continue;
            }

            $out[] = $line;
            if ($group !== null && preg_match('/^(allow|disallow|clean-param|crawl-delay|host)\s*:\s*(.*)$/i', $trim, $m)) {
                $group['lastRule'] = count($out) - 1;
                if (strtolower($m[1]) === 'disallow') {
                    $group['disallow'][] = rtrim(trim($m[2]), '*');
                }
            }
        }
        $flush();

        if (! array_filter($out, fn ($line) => preg_match('/^\s*user-agent\s*:/i', $line))) {
            array_unshift($out, 'User-agent: *', ...array_map(fn ($p) => 'Disallow: '.$p, self::REQUIRED_DISALLOW));
        }

        while ($out && trim(end($out)) === '') {
            array_pop($out);
        }

        return implode("\n", $out)."\n\nSitemap: ".$sitemapUrl."\n";
    }
}
