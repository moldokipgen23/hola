<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LaunchControlService;

class PlatformFeatureController extends Controller
{
    public function __invoke(LaunchControlService $launchControl)
    {
        return response()->json(['data' => $launchControl->publicConfig()]);
    }
}
