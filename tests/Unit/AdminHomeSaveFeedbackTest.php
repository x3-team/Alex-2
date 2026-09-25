<?php

namespace Tests\Unit;

use App\Models\HomeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminHomeSaveFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shares_empty_flash_until_something_is_saved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Home/Edit')
                ->where('flash.success', null)
                ->where('flash.error', null)
            );
    }

    public function test_successful_save_flashes_success_for_the_next_admin_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from('/admin/home')
            ->put('/admin/home', [
                'type' => 'doctor',
                'hero_title' => 'Заголовок из теста',
            ])
            ->assertRedirect('/admin/home')
            ->assertSessionHas('success', 'Настройки успешно сохранены.');

        $this->assertSame('Заголовок из теста', HomeSetting::getSettings('doctor')->hero_title);

        $this->actingAs($admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Настройки успешно сохранены.')
                ->where('flash.error', null)
                ->where('doctorSettings.hero_title', 'Заголовок из теста')
            );
    }

    public function test_validation_error_is_shared_and_does_not_overwrite_saved_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        HomeSetting::getSettings('doctor')->update(['why_title' => 'Как было']);

        $this->actingAs($admin)
            ->from('/admin/home')
            ->put('/admin/home', [
                'type' => 'doctor',
                'why_title' => str_repeat('Я', 256),
            ])
            ->assertRedirect('/admin/home')
            ->assertSessionHasErrors('why_title');

        $this->assertSame('Как было', HomeSetting::getSettings('doctor')->why_title);

        $this->actingAs($admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('errors.why_title', fn ($message) => is_string($message) && $message !== '')
                ->where('flash.success', null)
            );
    }

    public function test_error_flash_is_shared_with_inertia(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->withSession(['error' => 'Не удалось сохранить'])
            ->get('/admin/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.error', 'Не удалось сохранить')
                ->where('flash.success', null)
            );
    }
}
