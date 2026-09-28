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
}
