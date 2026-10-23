<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LabOffice;
use Illuminate\Http\Request;

/**
 * Список офисов для выбора в корзине. Пока заказ не боевой, отдаётся только админу;
 * публике — когда включены и ЛИС, и оплата.
 */
class CartOfficeController extends Controller
{
    public function index(Request $request)
    {
        $live = config('alfalab.enabled') && config('payments.enabled');
        if (! $live && ! $request->user()?->is_admin) {
            abort(404);
        }

        $offices = LabOffice::available()->orderBy('city')->orderBy('sort')->orderBy('name')->get();

        return response()->json([
            'cities' => $offices->groupBy('city')
                ->map(fn ($group, $city) => ['name' => $city, 'count' => $group->count()])
                ->values(),
            'offices' => $offices->map(fn (LabOffice $o) => $o->toCartArray())->values(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
