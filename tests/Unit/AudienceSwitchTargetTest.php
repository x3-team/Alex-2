<?php

namespace Tests\Unit;

use App\Support\AudienceSwitchTarget;
use PHPUnit\Framework\TestCase;

class AudienceSwitchTargetTest extends TestCase
{
    private function noArticles(): callable
    {
        return fn (string $audience, string $slug): bool => false;
    }

    public function test_service_pages_hide_the_switch(): void
    {
        $target = new AudienceSwitchTarget;

        $this->assertTrue($target->isHidden('/admin/blog'));
        $this->assertTrue($target->isHidden('/login'));
        $this->assertTrue($target->isHidden('/patient/login'));
        $this->assertTrue($target->isHidden('/cabinet'));
        $this->assertTrue($target->isHidden('/profile'));
        $this->assertFalse($target->isHidden('/quiz'));
        $this->assertFalse($target->isHidden('/blog/authors'));
        $this->assertFalse($target->isHidden('/alex-lab/doctors'));
    }

    public function test_shared_pages_keep_the_same_path(): void
    {
        $none = $this->noArticles();

        foreach (['/search', '/cart', '/quiz', '/demo-result', '/alex-lab', '/consent'] as $path) {
            $this->assertSame($path, AudienceSwitchTarget::pathFor($path, 'patients', $none));
            $this->assertSame($path, AudienceSwitchTarget::pathFor($path, 'doctors', $none));
        }
    }

    public function test_homes_point_at_each_other(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/', AudienceSwitchTarget::pathFor('/', 'patients', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/', 'doctors', $none));
    }

    public function test_patient_only_blog_falls_back_to_the_doctor_home(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/', AudienceSwitchTarget::pathFor('/blog', 'doctors', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/blog/authors', 'doctors', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/blog/author/4', 'doctors', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/blog/pollen', 'doctors', $none));
        $this->assertSame('/blog/pollen', AudienceSwitchTarget::pathFor('/blog/pollen', 'patients', $none));
    }

    public function test_article_with_the_other_audience_keeps_its_slug(): void
    {
        $both = fn (string $audience, string $slug): bool => $slug === 'ccd';

        $this->assertSame('/materials/ccd', AudienceSwitchTarget::pathFor('/blog/ccd', 'doctors', $both));
        $this->assertSame('/blog/ccd', AudienceSwitchTarget::pathFor('/materials/ccd', 'patients', $both));
    }

    public function test_doctor_only_sections_fall_back_to_the_patient_home(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/', AudienceSwitchTarget::pathFor('/materials', 'patients', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/materials/documents', 'patients', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/materials/licenses', 'patients', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/video', 'patients', $none));
        $this->assertSame('/', AudienceSwitchTarget::pathFor('/video/ige', 'patients', $none));
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/materials', 'doctors', $none));
        $this->assertSame('/video/ige', AudienceSwitchTarget::pathFor('/video/ige', 'doctors', $none));
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/doctor-materials', 'doctors', $none));
    }
}
