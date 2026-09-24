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

    public function test_blog_listings_point_at_each_other(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/blog', 'doctors', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/materials', 'patients', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/blog', 'patients', $none));
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/materials', 'doctors', $none));
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/doctor-materials', 'doctors', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/doctor-materials', 'patients', $none));
    }

    public function test_blog_categories_use_the_other_list_or_the_same_category(): void
    {
        $none = $this->noArticles();
        $pollen = fn (string $slug): bool => $slug === 'pollen';

        // У врачей нет разводящей категории: /blog/{slug} на их домене уходит на список.
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/blog/pollen', 'doctors', $none, $pollen));
        $this->assertSame('/blog/pollen', AudienceSwitchTarget::pathFor('/blog/pollen', 'patients', $none, $pollen));
        $this->assertSame('/blog/pollen', AudienceSwitchTarget::pathFor('/materials/pollen', 'patients', $none, $pollen));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/materials/licenses', 'patients', $none, $pollen));
    }

    public function test_authors_keep_the_same_page(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/blog/authors', AudienceSwitchTarget::pathFor('/blog/authors', 'doctors', $none));
        $this->assertSame('/blog/authors', AudienceSwitchTarget::pathFor('/blog/authors', 'patients', $none));
        $this->assertSame('/blog/author/4', AudienceSwitchTarget::pathFor('/blog/author/4', 'doctors', $none));
        $this->assertSame('/blog/author/4', AudienceSwitchTarget::pathFor('/blog/author/4', 'patients', $none));
    }

    public function test_article_without_a_twin_opens_the_other_blog_list(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/blog/kak-rabotaet-alex2', 'doctors', $none));
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/blog/vidy-allergii/allergiya-na-kozhe', 'doctors', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/materials/history-ige', 'patients', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/materials/documents', 'patients', $none));
    }

    public function test_article_with_the_other_audience_keeps_its_slug(): void
    {
        $both = fn (string $audience, string $slug): bool => $slug === 'ccd';

        $this->assertSame('/materials/ccd', AudienceSwitchTarget::pathFor('/blog/ccd', 'doctors', $both));
        $this->assertSame('/blog/ccd', AudienceSwitchTarget::pathFor('/materials/ccd', 'patients', $both));
        // Слаг со слэшем маршрут /materials/{category} не откроет — список, не 404.
        $slashed = fn (string $audience, string $slug): bool => $slug === 'vidy/post';
        $this->assertSame('/materials', AudienceSwitchTarget::pathFor('/blog/vidy/post', 'doctors', $slashed));
    }

    public function test_doctor_videos_and_documents_open_the_patient_blog(): void
    {
        $none = $this->noArticles();

        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/video', 'patients', $none));
        $this->assertSame('/blog', AudienceSwitchTarget::pathFor('/video/ige', 'patients', $none));
        $this->assertSame('/video/ige', AudienceSwitchTarget::pathFor('/video/ige', 'doctors', $none));
    }

    public function test_doctor_feed_type_is_not_carried_to_the_patient_blog(): void
    {
        $query = ['type' => 'videos', 'page' => '2', 'q' => 'ige'];

        $this->assertSame(
            ['page' => '2', 'q' => 'ige'],
            AudienceSwitchTarget::queryFor('/blog', $query),
        );
        $this->assertSame($query, AudienceSwitchTarget::queryFor('/materials', $query));
        $this->assertSame([], AudienceSwitchTarget::queryFor('/', $query));
    }
}
