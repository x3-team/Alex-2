<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\User;

/**
 * Public author URLs: /blog/author/{slug} from the person's name.
 */
final class AuthorSlug
{
    public static function fromName(string $name): string
    {
        $slug = Blog::slugifyTitle($name);

        return $slug !== '' ? $slug : 'author';
    }

    public static function unique(string $name, ?int $ignoreId = null): string
    {
        $base = self::fromName($name);
        $candidate = $base;
        $i = 2;

        while (
            User::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    public static function publicPath(?string $slug, int|string|null $id): string
    {
        $key = ($slug !== null && $slug !== '') ? $slug : (string) $id;

        return '/blog/author/'.$key;
    }
}
