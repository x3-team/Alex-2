<?php

namespace App\Support;

use App\Models\Setting;

class CartCatalog
{
    public static function mainProduct(): array
    {
        $product = [
            'id' => 'alex2',
            'title' => 'Сдача анализа ALEX2',
            'description' => 'Мультикомплексный анализ на 300 аллергенов за один забор крови. Включает в себя общий IgE',
            'price' => 24900.0,
            'tags' => ['+ Забор крови', '+IgE'],
        ];
        $saved = Setting::get('cart_main_product');
        $decoded = is_string($saved) ? json_decode($saved, true) : null;
        if (is_array($decoded)) {
            foreach (['title', 'description', 'price'] as $key) {
                if (array_key_exists($key, $decoded) && $decoded[$key] !== '' && $decoded[$key] !== null) {
                    $product[$key] = $decoded[$key];
                }
            }
        }
        $product['price'] = (float) $product['price'];

        return $product;
    }
}
