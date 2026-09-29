<?php

namespace Tests\Unit;

use Tests\TestCase;

class PatientBlogCoverChipsTest extends TestCase
{
    public function test_listing_chips_sit_under_the_cover(): void
    {
        $pages = [
            base_path('resources/js/Pages/Public/Blog/Index.vue'),
            base_path('resources/js/Pages/Public/Blog/Author.vue'),
            base_path('resources/js/Pages/Public/Blog/Show.vue'),
            base_path('resources/js/Components/DoctorFeedCard.vue'),
            base_path('resources/js/Components/DoctorVideoCard.vue'),
        ];
        $meta = file_get_contents(base_path('resources/js/Components/BlogFeedMeta.vue'));
        $css = file_get_contents(base_path('resources/css/main.css'));

        foreach ($pages as $page) {
            $source = file_get_contents($page);
            $this->assertStringContainsString('<BlogFeedMeta', $source);
            $this->assertStringNotContainsString('absolute top-2 left-2', $source);
            $this->assertStringNotContainsString('absolute top-3 left-3', $source);
            $this->assertStringNotContainsString('overlay', $source);
        }

        $this->assertStringContainsString('class="blog-feed-meta"', $meta);
        $this->assertStringNotContainsString('absolute', $meta);
        $this->assertStringContainsString('.blog-feed-meta {', $css);

        $videoCard = file_get_contents(base_path('resources/js/Components/DoctorVideoCard.vue'));
        $this->assertStringNotContainsString('doctor-video-card__badges', $videoCard);
    }
}
