<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\ContextCapture;

it('omits the ip by default and keeps the user agent', function () {
    config()->set('trail.privacy', ['anonymize_ip' => true, 'store_ip' => false, 'store_user_agent' => true]);
    $request = Request::create('/checkout', 'GET', server: ['REMOTE_ADDR' => '203.0.113.42', 'HTTP_USER_AGENT' => 'Mozilla/5.0']);
    $c = (new ContextCapture)->fromRequest($request);
    expect($c)->not->toHaveKey('ip')->and($c['user_agent'])->toBe('Mozilla/5.0')->and($c['url'])->toContain('/checkout');
});

it('anonymizes the ip when storing is enabled', function () {
    config()->set('trail.privacy', ['anonymize_ip' => true, 'store_ip' => true, 'store_user_agent' => false]);
    $request = Request::create('/x', 'GET', server: ['REMOTE_ADDR' => '203.0.113.42']);
    $c = (new ContextCapture)->fromRequest($request);
    expect($c['ip'])->toBe('203.0.113.0')->and($c)->not->toHaveKey('user_agent');
});

it('merges captured request context into tracked events', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.privacy', ['store_ip' => false, 'store_user_agent' => true]);

    Route::middleware('web')->get('/ctx-probe', function () {
        Trail::withContext(['custom' => 'x'])->track('page.viewed');

        return 'ok';
    });

    $this->get('/ctx-probe')->assertOk();

    $event = TrailEvent::firstWhere('name', 'page.viewed');

    expect($event->context)->toHaveKey('custom')
        ->and($event->context)->toHaveKey('url');
});
