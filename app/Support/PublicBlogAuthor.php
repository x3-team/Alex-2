<?php

namespace App\Support;

class PublicBlogAuthor
{
    public static function visible(?object $author): ?object
    {
        if ($author === null) {
            return null;
        }

        $isAdmin = $author->is_admin ?? false;

        return $isAdmin ? null : $author;
    }
}
