<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\HomeController;
use Illuminate\Http\Request;
use Inertia\Response;

class DoctorHomeController extends Controller
{
    /**
     * Keep the existing doctors storytelling home (Welcome + isDoctorRoute).
     * Do not redirect to materials — that page was already built separately.
     */
    public function __invoke(Request $request): Response
    {
        return app(HomeController::class)->doctorIndex($request);
    }
}
