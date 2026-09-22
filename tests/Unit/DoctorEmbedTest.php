<?php

namespace Tests\Unit;

use App\Support\DoctorEmbed;
use App\Support\DoctorMaterialsStore;
use PHPUnit\Framework\TestCase;

class DoctorEmbedTest extends TestCase
{
    public function test_youtube_watch_url_becomes_nocookie_embed(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            DoctorEmbed::iframeSrc('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
    }

    public function test_vk_watch_url_becomes_video_ext(): void
    {
        $this->assertSame(
            'https://vk.com/video_ext.php?oid=-123&id=456&hd=2',
            DoctorEmbed::iframeSrc('https://vk.com/video-123_456')
        );
    }

    public function test_unknown_url_is_rejected(): void
    {
        $this->assertNull(DoctorEmbed::iframeSrc('https://example.com/not-a-video'));
    }

    public function test_category_link_is_optional_and_normalized(): void
    {
        $this->assertSame('', DoctorMaterialsStore::normalizeCategoryLink('  '));
        $this->assertSame('/materials?type=documents', DoctorMaterialsStore::normalizeCategoryLink('/materials?type=documents'));
        $this->assertSame('https://eaaci.org/guide', DoctorMaterialsStore::normalizeCategoryLink('eaaci.org/guide'));
        $this->assertSame('https://eaaci.org/guide', DoctorMaterialsStore::normalizeCategoryLink('https://eaaci.org/guide'));
        $this->assertNull(DoctorMaterialsStore::normalizeCategoryLink('not a url'));
    }

    public function test_public_category_plaques_are_capped_at_six(): void
    {
        $this->assertSame(6, DoctorMaterialsStore::MAX_CATEGORIES);
    }

    public function test_files_are_sorted_by_category_then_sort_order(): void
    {
        $sorted = DoctorMaterialsStore::sortFiles([
            ['title' => 'B2', 'category_id' => 'b', 'sort_order' => 2],
            ['title' => 'A2', 'category_id' => 'a', 'sort_order' => 2],
            ['title' => 'A1', 'category_id' => 'a', 'sort_order' => 1],
            ['title' => 'B1', 'category_id' => 'b', 'sort_order' => 1],
        ]);

        $this->assertSame(['A1', 'A2', 'B1', 'B2'], array_column($sorted, 'title'));
    }

    public function test_document_count_label(): void
    {
        $this->assertSame('1 документ', DoctorMaterialsStore::ruDocuments(1));
        $this->assertSame('2 документа', DoctorMaterialsStore::ruDocuments(2));
        $this->assertSame('5 документов', DoctorMaterialsStore::ruDocuments(5));
        $this->assertSame('11 документов', DoctorMaterialsStore::ruDocuments(11));
        $this->assertSame('21 документ', DoctorMaterialsStore::ruDocuments(21));
    }

    public function test_patient_sitemap_source_does_not_list_doctor_only_paths(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/SitemapController.php');

        $this->assertIsString($src);
        $this->assertStringContainsString("'/blog'", $src);
        $this->assertStringNotContainsString("'/video'", $src);
        $this->assertStringNotContainsString("'/materials'", $src);
    }

    public function test_doctor_public_pages_are_forced_noindex_in_layout(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/resources/views/app.blade.php');

        $this->assertIsString($src);
        $this->assertStringContainsString('site.isDoctorsSite', $src);
        $this->assertStringContainsString('isDoctorsSite && $seoPath !== \'/\'', $src);
    }

    public function test_doctor_listing_lives_on_materials_without_blog_categories(): void
    {
        $blog = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/BlogController.php');
        $videos = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/DoctorVideoController.php');
        $docs = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/DoctorMaterialController.php');
        $chips = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/DoctorTypeChips.vue');
        $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Public/Blog/Index.vue');

        $this->assertIsString($blog);
        $this->assertStringContainsString("return redirect()->to(\$this->doctorMaterialsUrl(\$request->query()), 301);", $blog);
        $this->assertStringContainsString("\$url = '/materials';", $blog);
        $this->assertStringContainsString("'path' => \$request->root().'/materials'", $blog);
        $this->assertMatchesRegularExpression('/if \(\$isDoctors\) \{\s+\$category = null;/', $blog);
        $this->assertStringContainsString('publicCategories()', $blog);

        $this->assertIsString($videos);
        $this->assertStringContainsString("redirect()->to('/materials?type=videos', 301)", $videos);

        $this->assertIsString($docs);
        $this->assertStringContainsString("redirect()->to('/materials?type=documents', 301)", $docs);
        $this->assertStringContainsString('categoryBySlug', $docs);
        $this->assertStringContainsString("Public/DoctorMaterialCategory", $docs);

        $this->assertIsString($chips);
        $this->assertStringContainsString("{ key: 'all', label: 'Все', path: '/materials' }", $chips);
        $this->assertStringContainsString("{ key: 'videos', label: 'Видео', path: '/materials?type=videos' }", $chips);
        $this->assertStringNotContainsString("'/blog'", $chips);
        $this->assertStringNotContainsString('/materials/documents', $chips);

        $this->assertIsString($index);
        $this->assertStringContainsString("return doctorsUrl('/materials')", $index);
        $this->assertStringContainsString('v-if="!isDoctorMode"', $index);
        $this->assertStringContainsString('Выберите категорию', $index);
        $this->assertStringContainsString('DoctorDocumentCategoryCard', $index);
        $this->assertStringContainsString('articleUrl(item.slug)', $index);
    }

    public function test_doctor_article_permalinks_use_materials(): void
    {
        $blog = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/BlogController.php');
        $docs = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/DoctorMaterialController.php');
        $mode = file_get_contents(dirname(__DIR__, 2).'/resources/js/composables/useDoctorMode.js');

        $this->assertIsString($blog);
        $this->assertStringContainsString("return redirect()->to('/materials/'.\$slug, 301);", $blog);

        $this->assertIsString($docs);
        $this->assertStringContainsString('if ($articleExists)', $docs);
        $this->assertStringContainsString('BlogController::class)->show($categorySlug)', $docs);

        $this->assertIsString($mode);
        $this->assertStringContainsString('doctorsUrl(`/materials${path}`)', $mode);
        $this->assertStringContainsString('`/blog${path}`', $mode);
    }
}
