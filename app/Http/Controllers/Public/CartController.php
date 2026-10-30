<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CartService;
use App\Support\CartCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function index(Request $request)
    {
        // Корзина пока не боевая: публике — 404, админ видит страницу для доработки.
        if (! $request->user()?->is_admin) {
            abort(404);
        }

        $mainProduct = CartCatalog::mainProduct();

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

        $bridgeOn = (bool) config('order_bridge.enabled');
        $response = Inertia::render('Public/Cart', [
            'mainProduct' => $mainProduct,
            'additionalServices' => $additionalServices,
            'labs' => $labs,
            'orderBridge' => [
                'enabled' => $bridgeOn,
                'consentText' => $bridgeOn ? (string) config('order_bridge.consent_text') : '',
            ],
            'meta' => [
                'title' => 'Корзина — запись на тест ALEX²',
                'description' => 'Оформите запись на тест на аллергию ALEX².',
            ],
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}