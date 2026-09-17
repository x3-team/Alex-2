<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class DoctorMaterialController extends Controller
{
    public function index()
    {
        return app(BlogController::class)->index(request());
    }

    public function documents(): RedirectResponse
    {
        return redirect()->to('/materials?type=documents', 301);
    }

    public function category(string $categorySlug): RedirectResponse
    {
        return redirect()->to('/materials?type=documents', 301);
    }
}
