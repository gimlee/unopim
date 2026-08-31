<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    public function index(ExchangeRateService $service): JsonResponse
    {
        if ($service->payload()['rates'] === []) {
            $service->refresh(true);
        }

        return response()->json(['data' => $service->payload()]);
    }
}
