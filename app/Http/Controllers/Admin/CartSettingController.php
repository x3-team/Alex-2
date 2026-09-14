<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartSettingController extends Controller
{
    public function edit()
    {
        $services = CartService::orderBy('order')->get();

        $defaults = [
            'title' => 'Сдача анализа ALEX2',
            'description' => 'Мультикомплексный анализ на 300 аллергенов за один забор крови. Включает в себя общий IgE',
            'price' => 24900,
        ];
        $saved = Setting::get('cart_main_product');
        $decoded = is_string($saved) ? json_decode($saved, true) : null;
        $mainProduct = is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
        $mainProduct['price'] = isset($mainProduct['price']) ? (float) $mainProduct['price'] : 24900;

        return Inertia::render('Admin/CartSettings/Edit', [
            'mainProduct' => $mainProduct,
            'services' => $services,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'main_product.title' => 'required|string',
            'main_product.description' => 'required|string',
            'main_product.price' => 'required|numeric|min:0',
            'services' => 'required|array',
            'services.*.id' => 'nullable|exists:cart_services,id',
            'services.*.title' => 'required|string',
            'services.*.description' => 'required|string',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_active' => 'boolean',
        ]);


        Setting::set('cart_main_product', json_encode([
            'title' => $validated['main_product']['title'],
            'description' => $validated['main_product']['description'],
            'price' => $validated['main_product']['price'],
        ], JSON_UNESCAPED_UNICODE));

        // Синхронизируем дополнительные услуги
        $incomingIds = collect($validated['services'])->pluck('id')->filter();
        CartService::whereNotIn('id', $incomingIds)->delete();

        foreach ($validated['services'] as $index => $serviceData) {
            $serviceData['order'] = $index;
            if (!empty($serviceData['id'])) {
                CartService::where('id', $serviceData['id'])->update($serviceData);
            } else {
                unset($serviceData['id']);
                CartService::create($serviceData);
            }
        }

        return redirect()->back()->with('success', 'Настройки корзины успешно обновлены.');
    }
}