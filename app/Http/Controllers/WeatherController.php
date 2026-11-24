<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lon' => ['required', 'numeric', 'between:-180,180']]);
        $key = config('services.openweather.key');
        if (! $key) {
            return response()->json(['message' => 'Weather is unavailable until the server API key is configured.'], 503);
        }
        $cacheKey = 'membership-weather:'.hash('sha256', round((float) $data['lat'], 4).':'.round((float) $data['lon'], 4).':'.$key);
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }
        try {
            $response = Http::connectTimeout(3)->timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', ['lat' => $data['lat'], 'lon' => $data['lon'], 'units' => 'metric', 'appid' => $key]);
        } catch (ConnectionException $exception) {
            return response()->json(['message' => 'The weather service could not be reached. Please try again later.'], 503);
        }
        if (! $response->successful()) {
            return response()->json(['message' => 'Weather is currently unavailable. Please try again later.'], 503);
        }
        $weather = $response->json();
        if (! is_array($weather) || ! is_numeric(data_get($weather, 'main.temp')) || ! is_numeric(data_get($weather, 'main.humidity'))) {
            return response()->json(['message' => 'Weather is currently unavailable. Please try again later.'], 503);
        }
        $result = ['place' => $weather['name'] ?? 'Selected location', 'temperature' => data_get($weather, 'main.temp'), 'description' => data_get($weather, 'weather.0.description'), 'humidity' => data_get($weather, 'main.humidity')];
        Cache::put($cacheKey, $result, now()->addMinutes(5));

        return response()->json($result);
    }
}
