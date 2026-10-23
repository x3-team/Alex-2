<?php

namespace Tests\Unit;

use App\Models\CartService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCartServicesSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    private function payload(array $services, array $removedIds = []): array
    {
        return [
            'main_product' => ['title' => 'Сдача анализа ALEX2', 'description' => 'Описание', 'price' => 24900],
            'services' => $services,
            'removed_ids' => $removedIds,
        ];
    }

    private function row(CartService $service, array $override = []): array
    {
        return array_merge($service->only(['id', 'title', 'description']) + [
            'price' => (float) $service->price,
            'is_active' => (bool) ($service->is_active ?? true),
        ], $override);
    }

    public function test_service_missing_from_form_is_kept(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $consultation = CartService::create(['title' => 'Консультация', 'description' => 'd', 'price' => 2000]);
        $urgent = CartService::create(['title' => 'Срочный результат', 'description' => 'd', 'price' => 5000]);

        // Старая вкладка: в форме только одна услуга, вторую никто не удалял.
        $this->actingAs($admin)
            ->put(route('admin.cart-settings.update'), $this->payload([$this->row($consultation, ['price' => 2500])]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('2500.00', $consultation->fresh()->price);
        $this->assertNotNull($urgent->fresh());
        $this->assertSame(2, CartService::count());
    }

    public function test_explicitly_removed_service_is_soft_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $consultation = CartService::create(['title' => 'Консультация', 'description' => 'd', 'price' => 2000]);
        $urgent = CartService::create(['title' => 'Срочный результат', 'description' => 'd', 'price' => 5000]);

        $this->actingAs($admin)
            ->put(route('admin.cart-settings.update'), $this->payload([$this->row($consultation)], [$urgent->id]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(CartService::find($urgent->id));
        $this->assertNotNull(CartService::withTrashed()->find($urgent->id));
        $this->assertNotNull(CartService::find($consultation->id));
    }

    public function test_id_both_kept_and_removed_is_not_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $consultation = CartService::create(['title' => 'Консультация', 'description' => 'd', 'price' => 2000]);

        $this->actingAs($admin)
            ->put(route('admin.cart-settings.update'), $this->payload([$this->row($consultation)], [$consultation->id]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotNull(CartService::find($consultation->id));
    }

    public function test_new_service_is_created(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.cart-settings.update'), $this->payload([
                ['id' => null, 'title' => 'Новая', 'description' => 'd', 'price' => 100, 'is_active' => true],
            ]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(['Новая'], CartService::pluck('title')->all());
    }
}
