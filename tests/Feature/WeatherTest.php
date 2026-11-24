<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('validates coordinate bounds before contacting the provider', function () {
    Http::fake();
    $this->getJson(route('weather.show', ['lat' => 91, 'lon' => 181]))->assertUnprocessable()->assertJsonValidationErrors(['lat', 'lon']);
    Http::assertNothingSent();
});

it('returns a helpful error if the server key is missing', function () {
    config(['services.openweather.key' => null]);
    Http::fake();
    $this->getJson(route('weather.show', ['lat' => 14.5995, 'lon' => 120.9842]))->assertStatus(503)->assertJsonStructure(['message']);
    Http::assertNothingSent();
});

it('retrieves and caches weather while keeping the secret out of the response', function () {
    config(['services.openweather.key' => 'private-weather-secret']);
    Http::fake(['api.openweathermap.org/*' => Http::response(['name' => 'Manila', 'main' => ['temp' => 29.5, 'humidity' => 70], 'weather' => [['description' => 'clear sky']]])]);
    $url = route('weather.show', ['lat' => 14.5995, 'lon' => 120.9842]);
    $this->getJson($url)->assertOk()->assertExactJson(['place' => 'Manila', 'temperature' => 29.5, 'description' => 'clear sky', 'humidity' => 70])->assertDontSee('private-weather-secret');
    $this->getJson($url)->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['appid'] === 'private-weather-secret' && $request['units'] === 'metric');
});

it('hides provider errors and handles malformed weather responses', function () {
    config(['services.openweather.key' => 'secret-provider-key']);
    Http::fake(['*' => Http::response(['message' => 'Bad secret-provider-key'], 401)]);
    $url = route('weather.show', ['lat' => 14.5995, 'lon' => 120.9842]);
    $this->getJson($url)->assertStatus(503)->assertDontSee('secret-provider-key');
    Http::fake(['*' => Http::response(['main' => []])]);
    $this->getJson($url)->assertStatus(503);
});

it('handles timeouts without exposing the request or key', function () {
    config(['services.openweather.key' => 'secret-timeout-key']);
    Http::fake(fn () => throw new ConnectionException('connection failed secret-timeout-key'));
    $this->getJson(route('weather.show', ['lat' => 14.5995, 'lon' => 120.9842]))->assertStatus(503)->assertDontSee('secret-timeout-key');
});

it('rate limits weather without consuming the public application form limit', function () {
    config(['services.openweather.key' => null]);
    $url = route('weather.show', ['lat' => 14.5995, 'lon' => 120.9842]);
    for ($i = 0; $i < 30; $i++) {
        $this->getJson($url)->assertStatus(503);
    }
    $this->getJson($url)->assertTooManyRequests();
    $this->postJson(route('membership.store'), [])->assertUnprocessable();
});
