<?php

namespace Tests\Unit;

use Tests\TestCase;

class DoctorVideoCoverTest extends TestCase
{
    public function test_opened_video_shows_the_full_cover(): void
    {
        $vue = file_get_contents(base_path('resources/js/Pages/Public/DoctorVideos/Show.vue'));

        $this->assertIsString($vue);
        $this->assertStringContainsString('aspect-ratio: 2 / 1', $vue);
        $this->assertStringContainsString('object-fit: contain', $vue);
        $this->assertStringNotContainsString('aspect-ratio: 1115 / 627', $vue);
    }

    public function test_listing_covers_keep_the_full_frame_on_mobile(): void
    {
        $css = file_get_contents(base_path('resources/css/main.css'));
        $card = file_get_contents(base_path('resources/js/Components/DoctorVideoCard.vue'));

        $this->assertStringContainsString('aspect-ratio: 2 / 1', $css);
        $this->assertStringContainsString(".blog-feed-cover > img {\n    display: block;\n    width: 100%;\n    height: 100%;\n    object-fit: contain;", $css);
        $this->assertStringContainsString('aspect-ratio: 2 / 1', $card);
        $this->assertStringContainsString('object-fit: contain', $card);
        $this->assertStringNotContainsString('object-fit: cover', $card);
    }
}
