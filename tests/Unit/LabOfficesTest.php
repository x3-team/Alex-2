<?php

namespace Tests\Unit;

use App\Models\LabOffice;
use App\Models\User;
use App\Support\LabOfficeCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LabOfficesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2).'/storage/framework/views';
        if (! is_dir($views)) {
            mkdir($views, 0777, true);
        }

        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        config(['alfalab.enabled' => false, 'payments.enabled' => false]);
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function office(array $attrs = []): LabOffice
    {
        return LabOffice::create($attrs + [
            'city' => 'Москва', 'name' => 'Офис 1', 'address' => 'ул. Ленина, 1',
            'accepts_alex2' => true, 'is_active' => true,
        ]);
    }

    public function test_cart_offices_is_404_for_guest_and_user_while_flags_off(): void
    {
        $this->office();

        $this->getJson('/cart/offices')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->getJson('/cart/offices')->assertNotFound();

        config(['alfalab.enabled' => true]);
        auth()->logout();
        $this->getJson('/cart/offices')->assertNotFound();
    }

    public function test_cart_offices_open_to_public_when_both_flags_on(): void
    {
        config(['alfalab.enabled' => true, 'payments.enabled' => true]);
        $this->office();

        $this->getJson('/cart/offices')->assertOk()->assertJsonCount(1, 'offices');
    }

    public function test_admin_gets_only_available_offices_in_cart_shape(): void
    {
        $this->office(['city' => 'Москва', 'name' => 'Б', 'lat' => 55.7935, 'lng' => 37.4939, 'hours_weekdays' => '9–19', 'sort' => 2]);
        $this->office(['city' => 'Москва', 'name' => 'А', 'address' => 'Тверская, 5', 'sort' => 1]);
        $this->office(['city' => 'Казань', 'name' => 'К']);
        $this->office(['name' => 'Выключенный', 'is_active' => false]);
        $this->office(['name' => 'Без ALEX2', 'accepts_alex2' => false]);

        $response = $this->actingAs($this->admin())->getJson('/cart/offices');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertJsonPath('cities', [['name' => 'Казань', 'count' => 1], ['name' => 'Москва', 'count' => 2]])
            ->assertJsonPath('offices.0.name', 'К')
            ->assertJsonPath('offices.1.name', 'А')
            ->assertJsonPath('offices.2.name', 'Б')
            ->assertJsonPath('offices.2.hours.weekdays', '9–19')
            ->assertJsonPath('offices.2.map_url', 'https://yandex.ru/maps/?pt=37.493900,55.793500&z=16&l=map')
            ->assertJsonStructure(['offices' => [['id', 'city', 'name', 'address', 'metro', 'phone', 'hours' => ['weekdays', 'weekend'], 'badge', 'note', 'lat', 'lng', 'map_url']]]);

        $this->assertStringStartsWith('https://yandex.ru/maps/?text=', $response->json('offices.1.map_url'));
        $this->assertStringContainsString(rawurlencode('Москва, Тверская, 5'), $response->json('offices.1.map_url'));
    }

    public function test_non_admin_cannot_open_admin_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $office = $this->office();

        $this->get('/admin/lab-offices')->assertRedirect();
        $this->actingAs($user)->get('/admin/lab-offices')->assertForbidden();
        $this->actingAs($user)->post('/admin/lab-offices', ['city' => 'X', 'name' => 'Y', 'address' => 'Z'])->assertForbidden();
        $this->actingAs($user)->post("/admin/lab-offices/{$office->id}/toggle")->assertForbidden();
        $this->actingAs($user)->get('/admin/lab-offices/template.csv')->assertForbidden();
        $this->assertTrue($office->fresh()->is_active);
    }

    public function test_admin_index_with_filters(): void
    {
        $this->office(['city' => 'Москва']);
        $this->office(['city' => 'Казань', 'is_active' => false]);

        $this->actingAs($this->admin())->get('/admin/lab-offices?status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/LabOffices/Index', false)
                ->has('offices', 1)
                ->where('offices.0.city', 'Казань')
                ->where('cities', ['Казань', 'Москва']));
    }

    public function test_admin_creates_updates_and_toggles_office(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/lab-offices', [
            'city' => 'Москва', 'name' => 'Новый', 'address' => 'ул. Новая, 3', 'lat' => '55.1', 'lng' => '37.2',
            'lis_code' => 'MSK-3', 'accepts_alex2' => true, 'is_active' => true, 'sort' => 5,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $office = LabOffice::where('lis_code', 'MSK-3')->firstOrFail();
        $this->assertSame(5, $office->sort);
        $this->assertSame(55.1, $office->lat);

        $this->actingAs($admin)->put("/admin/lab-offices/{$office->id}", [
            'city' => 'Москва', 'name' => 'Переименован', 'address' => 'ул. Новая, 3', 'lis_code' => 'MSK-3',
            'lat' => '', 'lng' => '', 'accepts_alex2' => false, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $office->refresh();
        $this->assertSame('Переименован', $office->name);
        $this->assertFalse($office->accepts_alex2);
        $this->assertNull($office->lat);

        $this->actingAs($admin)->post("/admin/lab-offices/{$office->id}/toggle");
        $this->assertFalse($office->fresh()->is_active);
        $this->actingAs($admin)->post("/admin/lab-offices/{$office->id}/toggle");
        $this->assertTrue($office->fresh()->is_active);
    }

    public function test_admin_validation(): void
    {
        $admin = $this->admin();
        $this->office(['lis_code' => 'DUP']);

        $this->actingAs($admin)->post('/admin/lab-offices', ['city' => '', 'name' => 'X', 'address' => 'Y'])
            ->assertSessionHasErrors('city');
        $this->actingAs($admin)->post('/admin/lab-offices', ['city' => 'A', 'name' => 'X', 'address' => 'Y', 'lat' => '95', 'lng' => '10'])
            ->assertSessionHasErrors('lat');
        $this->actingAs($admin)->post('/admin/lab-offices', ['city' => 'A', 'name' => 'X', 'address' => 'Y', 'lat' => '55'])
            ->assertSessionHasErrors('lng');
        $this->actingAs($admin)->post('/admin/lab-offices', ['city' => 'A', 'name' => 'X', 'address' => 'Y', 'lis_code' => 'DUP'])
            ->assertSessionHasErrors('lis_code');

        $this->assertSame(1, LabOffice::count());
    }

    public function test_template_download_and_roundtrip(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/lab-offices/template.csv');

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="lab-offices-template.csv"');
        $this->assertStringStartsWith("\xEF\xBB\xBF\"Город\";\"Название\"", $response->getContent());

        $result = LabOfficeCsv::import($response->getContent());
        $this->assertSame(['created' => 1, 'updated' => 0, 'errors' => []], $result);
        $office = LabOffice::firstOrFail();
        $this->assertSame('Октябрьское поле', $office->metro);
        $this->assertSame(55.7935, $office->lat);
        $this->assertTrue($office->accepts_alex2);
    }

    public function test_import_russian_headers_cp1251_upsert_and_row_errors(): void
    {
        $byCode = $this->office(['lis_code' => 'K-1', 'name' => 'Старое имя', 'phone' => '+7 000']);
        $byAddress = $this->office(['city' => 'Казань', 'name' => 'Казань старое', 'address' => 'ул.  Баумана, 10']);

        $csv = implode("\r\n", [
            'Город;Название;Адрес;Код в ЛИС;Принимает ALEX2;Широта;Долгота',
            'Москва;Новое имя;ул. Ленина, 1;K-1;да;;',
            'Казань;Казань новое;УЛ. БАУМАНА, 10;;нет;55,79;49,12',
            'Сочи;Сочи;Курортный пр., 1;;;;',
            ';Без города;адрес;;;;',
            'Тула;Координата;Ленина 2;;;55;',
        ]);

        $result = LabOfficeCsv::import(mb_convert_encoding($csv, 'Windows-1251', 'UTF-8'));

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringStartsWith('Строка 5:', $result['errors'][0]);
        $this->assertStringStartsWith('Строка 6:', $result['errors'][1]);

        $byCode->refresh();
        $this->assertSame('Новое имя', $byCode->name);
        $this->assertSame('+7 000', $byCode->phone, 'колонки, которых нет в файле, не затираются');

        $byAddress->refresh();
        $this->assertSame('Казань новое', $byAddress->name);
        $this->assertFalse($byAddress->accepts_alex2);
        $this->assertSame(55.79, $byAddress->lat);

        $this->assertTrue(LabOffice::where('city', 'Сочи')->firstOrFail()->accepts_alex2);
        $this->assertSame(3, LabOffice::count());
    }

    public function test_import_rejects_missing_columns_and_empty_file(): void
    {
        $this->assertSame(['Файл пустой'], LabOfficeCsv::import('')['errors']);
        $this->assertStringContainsString('Адрес', LabOfficeCsv::import("city,name\nМосква,Офис")['errors'][0]);
        $this->assertSame(0, LabOffice::count());
    }

    public function test_admin_import_upload_flashes_result(): void
    {
        $file = UploadedFile::fake()->createWithContent('offices.csv', "city,name,address\nМосква,Офис,Тверская 1\n,Плохой,ул\n");

        $this->actingAs($this->admin())
            ->post('/admin/lab-offices/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('error', 'Импорт: добавлено 1, обновлено 0, с ошибками: 1')
            ->assertSessionHas('import_errors');

        $this->assertSame(1, LabOffice::count());
    }
}
