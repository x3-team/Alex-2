<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\DoctorMaterialController;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class DoctorMaterialsController extends Controller
{
    public function index()
    {
        return app(DoctorMaterialController::class)->index();
    }

    public function documents(): RedirectResponse
    {
        return app(DoctorMaterialController::class)->documents();
    }

    public function category(string $category)
    {
        return app(DoctorMaterialController::class)->category($category);
    }

    public function legacy(): Response
    {
        return $this->index();
    }
}
