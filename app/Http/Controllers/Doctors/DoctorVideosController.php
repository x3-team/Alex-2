<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\DoctorVideoController;
use Inertia\Response;

class DoctorVideosController extends Controller
{
    public function index()
    {
        return app(DoctorVideoController::class)->index();
    }

    public function show(string $slug): Response
    {
        return app(DoctorVideoController::class)->show($slug);
    }
}
