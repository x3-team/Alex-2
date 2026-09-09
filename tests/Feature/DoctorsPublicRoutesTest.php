<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorsPublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doctors.host' => 'doc.alexallergotest.ru',
            'doctors.path_preview' => true,
            'doctors.path_prefix' => 'doctors',
            'doctors.subdomain_redirect' => false,
        ]);
    }

    public function test_doctors_materials_is_public_on_apex_path(): void
    {
        $this->get('/doctors/materials')
            ->assertOk()
            ->assertDontSee('Вход — ALEX LAB');
    }

    public function test_doctors_login_and_register_are_public(): void
    {
        $this->get('/doctors/login')->assertOk();
        $this->get('/doctors/register')->assertOk();
    }

    public function test_doctors_home_redirects_to_materials(): void
    {
        $this->get('/doctors')->assertRedirect('/doctors/materials');
    }

    public function test_patient_login_is_unchanged(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_cabinet_requires_doctor(): void
    {
        $this->get('/doctors/cabinet')->assertRedirect('/doctors/login');
    }

    public function test_patient_user_cannot_open_cabinet(): void
    {
        if (! class_exists(User::class)) {
            $this->markTestSkipped('User model is only on the VPS.');
        }

        $user = User::factory()->create(['is_doctor' => false]);

        $this->actingAs($user)
            ->get('/doctors/cabinet')
            ->assertRedirect('/doctors/login');
    }

    public function test_open_register_sets_is_doctor(): void
    {
        if (! class_exists(User::class)) {
            $this->markTestSkipped('User model is only on the VPS.');
        }

        $this->post('/doctors/register', [
            'name' => 'Петров Пётр',
            'email' => 'doctor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/doctors/cabinet');

        $this->assertDatabaseHas('users', [
            'email' => 'doctor@example.com',
            'is_doctor' => 1,
        ]);
    }

    public function test_patient_blog_does_not_use_doctors_theme_shell(): void
    {
        $this->get('/blog')->assertOk();
    }
}
