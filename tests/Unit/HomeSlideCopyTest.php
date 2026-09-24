<?php

namespace Tests\Unit;

use App\Support\HomeSlideCopy;
use PHPUnit\Framework\TestCase;

class HomeSlideCopyTest extends TestCase
{
    private function welcomeVue(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Public/Welcome.vue');
    }

    public function test_empty_settings_are_seeded_with_the_current_wording(): void
    {
        $filled = HomeSlideCopy::fillMissing([], 'patient');

        $this->assertSame([
            'hero_title' => 'Тест на аллергию ALEX² — один анализ, который даёт ответы',
            'why_title' => 'Почему ALEX2?',
            'results_intro_title' => 'Что вы получите по итогам теста на аллергию',
            'cta_text' => 'Записаться на тест на аллергию',
        ], $filled);
    }

    public function test_doctor_wording_differs_from_patient(): void
    {
        $doctor = HomeSlideCopy::fillMissing([], 'doctor');

        $this->assertSame('ALEX² — лучший тест на аллергию, что есть на рынке.', $doctor['why_title']);
        $this->assertSame('Как назначать тест пациентам', $doctor['results_intro_title']);
        $this->assertNotSame(
            HomeSlideCopy::defaults('patient')['hero_title'],
            $doctor['hero_title']
        );
    }

    public function test_text_edited_in_admin_is_never_overwritten(): void
    {
        $filled = HomeSlideCopy::fillMissing([
            'hero_title' => 'Свой заголовок',
            'why_title' => 'Свой второй экран',
            'results_intro_title' => 'Свои результаты',
            'cta_text' => 'Своя кнопка',
        ], 'doctor');

        $this->assertSame([], $filled);
    }

    public function test_blank_and_missing_values_count_as_empty(): void
    {
        $filled = HomeSlideCopy::fillMissing([
            'hero_title' => '   ',
            'why_title' => null,
            'cta_text' => 'Своя кнопка',
        ], 'patient');

        $this->assertArrayHasKey('hero_title', $filled);
        $this->assertArrayHasKey('why_title', $filled);
        $this->assertArrayHasKey('results_intro_title', $filled);
        $this->assertArrayNotHasKey('cta_text', $filled);
    }

    public function test_front_end_fallback_repeats_the_same_wording(): void
    {
        // The page must look identical when a field is still empty, so the
        // Vue fallback and the seeded copy cannot drift apart.
        $welcome = $this->welcomeVue();

        foreach (['patient', 'doctor'] as $audience) {
            foreach (['hero_title', 'why_title', 'results_intro_title'] as $field) {
                $this->assertStringContainsString(
                    HomeSlideCopy::defaults($audience)[$field],
                    $welcome,
                    "Welcome.vue потерял запасной текст {$field} для {$audience}"
                );
            }
        }
    }

    public function test_slides_read_the_new_fields(): void
    {
        $welcome = $this->welcomeVue();

        $this->assertStringContainsString("copy('hero_title', 'slide-1')", $welcome);
        $this->assertStringContainsString("copy('why_title', 'slide-2')", $welcome);
        $this->assertStringContainsString("copy('results_intro_title', 'slide-8')", $welcome);
        // The frozen doctor overrides are what blocked editing in the admin.
        $this->assertStringNotContainsString('doctorSlideCopy', $welcome);
    }

    public function test_both_audiences_are_served_and_validated(): void
    {
        $public = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Public/HomeController.php');
        $admin = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Admin/HomeController.php');

        $this->assertSame(2, substr_count($public, "'why_title'"));
        $this->assertSame(2, substr_count($public, "'results_intro_title'"));
        $this->assertStringContainsString("'why_title'     => 'nullable|string|max:255'", $admin);
        $this->assertStringContainsString("'results_intro_title' => 'nullable|string|max:255'", $admin);
    }
}
