<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

covers(HealthController::class, SecurityHeaders::class);

describe('HealthController', function () {
    it('reports ok when the database and cache respond', function () {
        $this->getJson(route('health'))->assertOk()->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'cache' => true]]);
    });

    it('reports degraded with 503 when the cache fails', function () {
        Cache::shouldReceive('put')->andThrow(new RuntimeException('redis down'));

        $this->getJson(route('health'))->assertStatus(503)->assertJson(['status' => 'degraded', 'checks' => ['database' => true, 'cache' => false]]);
    });

    it('reports degraded with 503 when the database fails', function () {
        DB::shouldReceive('select')->andThrow(new RuntimeException('db down'));

        $this->getJson(route('health'))->assertStatus(503)->assertJson(['checks' => ['database' => false]]);
    });

    it('is reachable without logging in', function () {
        $this->get('/health')->assertOk();
    });
});

describe('SecurityHeaders', function () {
    function passThrough(Request $request): Response
    {
        return (new SecurityHeaders)->handle($request, fn () => new Response('ok'));
    }

    it('adds the hardening headers to every response', function () {
        $response = passThrough(Request::create('http://localhost/x'));

        expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
            ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    });

    it('adds HSTS only on secure requests', function () {
        expect(passThrough(Request::create('http://localhost/x'))->headers->has('Strict-Transport-Security'))->toBeFalse()
            ->and(passThrough(Request::create('https://localhost/x'))->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
    });

    it('does not alter the response body or status', function () {
        $response = (new SecurityHeaders)->handle(Request::create('/x'), fn () => new Response('payload', 201));

        expect($response->getContent())->toBe('payload')->and($response->getStatusCode())->toBe(201);
    });

    it('is applied to web responses through the kernel', function () {
        $this->get(route('login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    });
});
