<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DoctorCabinetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Doctors/Cabinet/Index');
    }
}
