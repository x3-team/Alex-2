<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\DoctorMaterialController;
use Inertia\Response;

class DoctorMaterialsController extends Controller
{
    public function index(): Response
    {
        return app(DoctorMaterialController::class)->index();
    }

    public function documents(): Response
    {
        return app(DoctorMaterialController::class)->documents();
    }

    public function category(string $category): Response
    {
        return app(DoctorMaterialController::class)->category($category);
    }

    public function legacy(): Response
    {
        return $this->index();
    }
}
