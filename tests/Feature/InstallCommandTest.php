<?php

declare(strict_types=1);

it('publishes the config and scaffolds the dashboard gate provider', function () {
    $config = config_path('trail.php');
    $provider = app_path('Providers/TrailServiceProvider.php');

    foreach ([$config, $provider] as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $this->artisan('trail:install')->assertSuccessful();

    expect(file_exists($config))->toBeTrue()
        ->and(file_exists($provider))->toBeTrue()
        ->and((string) file_get_contents($provider))->toContain('Trail::auth');

    unlink($config);
    unlink($provider);
});
