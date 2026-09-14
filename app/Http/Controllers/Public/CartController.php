<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CartService;
use App\Models\Setting;
use Inertia\Inertia;

class CartController extends Controller
{
    public function index()
    {
        $defaults = [
            'id' => 'alex2',
            'title' => 'Сдача анализа ALEX2',
            'description' => 'Мультикомплексный анализ на 300 аллергенов за один забор крови. Включает в себя общий IgE',
            'price' => 24900,
            'tags' => ['+ Забор крови', '+IgE'],
        ];
        $saved = Setting::get('cart_main_product');
        $decoded = is_string($saved) ? json_decode($saved, true) : null;
        $mainProduct = $defaults;
        if (is_array($decoded)) {
            foreach (['title', 'description', 'price'] as $key) {
                if (array_key_exists($key, $decoded) && $decoded[$key] !== '' && $decoded[$key] !== null) {
                    $mainProduct[$key] = $decoded[$key];
                }
            }
        }
        $mainProduct['price'] = (float) $mainProduct['price'];

        // 2. Дополнительные услуги из базы данных (преобразуем в массив для гарантии)
        $additionalServices = CartService::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'title', 'description', 'price'])
            ->toArray();

        // 3. Лаборатории (пример данных, замените на запрос к вашей модели LabLocation, если она есть)
        $labs = [
            [
                'id' => 1, 'city' => 'Москва', 'name' => 'ALEX, Маршала Бирюзова 9',
                'hoursWeekdays' => '9:00 – 19:00', 'hoursWeekend' => 'Выходные',
                'discount' => true, 'x' => 453, 'y' => 49, 'desktopX' => 383, 'desktopY' => 126
            ],
            [
                'id' => 2, 'city' => 'Москва', 'name' => 'ALEX, Ленинградская 4',
                'hoursWeekdays' => '9:00 – 19:00', 'hoursWeekend' => '11:00 – 16:00',
                'discount' => true, 'x' => 865, 'y' => -391, 'desktopX' => 700, 'desktopY' => 349
            ]
        ];

        return Inertia::render('Public/Cart', [
            'mainProduct' => $mainProduct,
            'additionalServices' => $additionalServices,
            'labs' => $labs,
            'meta' => [
                'title' => 'Корзина — запись на тест ALEX²',
                'description' => 'Оформите запись на тест на аллергию ALEX².',
            ],
        ]);
    }
}