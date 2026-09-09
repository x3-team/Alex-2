<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Services\DetectSite;
use Illuminate\Http\RedirectResponse;

class DoctorHomeController extends Controller
{
    public function __invoke(DetectSite $detectSite): RedirectResponse
    {
        return redirect($detectSite->doctorsUrl('/materials'));
    }
}
