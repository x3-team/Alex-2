<?php

namespace Tests\Unit;

use App\Support\PublicBlogAuthor;
use PHPUnit\Framework\TestCase;

class PublicBlogAuthorTest extends TestCase
{
    public function test_missing_author_is_hidden(): void
    {
        $this->assertNull(PublicBlogAuthor::visible(null));
    }

    public function test_admin_author_is_hidden(): void
    {
        $admin = (object) ['id' => 1, 'name' => 'Admin', 'is_admin' => true];

        $this->assertNull(PublicBlogAuthor::visible($admin));
    }

    public function test_named_author_is_visible(): void
    {
        $author = (object) ['id' => 4, 'name' => 'Эксперт', 'is_admin' => false];

        $this->assertSame($author, PublicBlogAuthor::visible($author));
    }
}
