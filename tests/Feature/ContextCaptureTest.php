<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Trail\Trail\Contracts\ContextCaptureContract;
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

it('captures hostname and pid from console by default', function () {
    config()->set('trail.console', [
        'capture_hostname' => true,
        'capture_pid' => true,
        'capture_command' => false,
        'capture_command_arguments' => false,
        'capture_server_ip' => false,
    ]);

    $c = (new ContextCapture)->fromConsole();

    expect($c)->toHaveKey('hostname')
        ->and($c['hostname'])->toBe(gethostname())
        ->and($c)->toHaveKey('pid')
        ->and($c['pid'])->toBeInt();
});

it('captures command name without arguments by default', function () {
    config()->set('trail.console', [
        'capture_hostname' => false,
        'capture_pid' => false,
        'capture_command' => true,
        'capture_command_arguments' => false,
        'capture_server_ip' => false,
    ]);

    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=default'];

    $c = (new ContextCapture)->fromConsole();

    expect($c)->toHaveKey('command')
        ->and($c['command'])->toBe('queue:work')
        ->and($c)->not->toHaveKey('command_arguments');
});

it('captures command arguments when opted in', function () {
    config()->set('trail.console', [
        'capture_hostname' => false,
        'capture_pid' => false,
        'capture_command' => true,
        'capture_command_arguments' => true,
        'capture_server_ip' => false,
    ]);

    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=default', '--tries=3'];

    $c = (new ContextCapture)->fromConsole();

    expect($c['command_arguments'])->toBe('--queue=default --tries=3');
});

it('omits server ip unless opted in', function () {
    config()->set('trail.console', [
        'capture_hostname' => false,
        'capture_pid' => false,
        'capture_command' => false,
        'capture_command_arguments' => false,
        'capture_server_ip' => false,
    ]);

    $c = (new ContextCapture)->fromConsole();

    expect($c)->not->toHaveKey('server_ip');
});

it('extracts present utm params as flat keys', function () {
    $request = Request::create('/landing', 'GET', [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'spring',
    ]);

    $c = (new ContextCapture)->fromRequest($request);

    expect($c['utm_source'])->toBe('newsletter')
        ->and($c['utm_medium'])->toBe('email')
        ->and($c['utm_campaign'])->toBe('spring')
        ->and($c)->not->toHaveKey('utm_term')
        ->and($c)->not->toHaveKey('utm_content');
});

it('omits utm params that are absent or empty', function () {
    $request = Request::create('/landing', 'GET', ['utm_source' => '']);

    $c = (new ContextCapture)->fromRequest($request);

    expect($c)->not->toHaveKey('utm_source')
        ->and($c)->not->toHaveKey('utm_medium');
});

it('resolves custom context capture class from config', function () {
    $custom = new class implements ContextCaptureContract
    {
        public function fromRequest(Request $request): array
        {
            return ['custom' => 'request'];
        }

        public function fromConsole(): array
        {
            return ['custom' => 'console'];
        }
    };

    config()->set('trail.context_capture', $custom::class);
    app()->bind($custom::class, fn () => $custom);
    app()->forgetInstance(ContextCaptureContract::class);

    $resolved = app(ContextCaptureContract::class);

    expect($resolved->fromConsole())->toBe(['custom' => 'console']);
});
