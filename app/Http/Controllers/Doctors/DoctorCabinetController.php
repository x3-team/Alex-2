<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Services\DetectSite;
use Inertia\Inertia;
use Inertia\Response;

class DoctorCabinetController extends Controller
{
    public function index(DetectSite $detectSite): Response
    {
        return Inertia::render('Doctors/Cabinet/Index', [
            'site' => [
                'mode' => $detectSite->mode(),
                'isDoctorsSite' => true,
                'themeColor' => $detectSite->themeColor(),
                'routePrefix' => $detectSite->routePrefix(),
            ],
        ]);
    }
}
