<?php

declare(strict_types=1);

namespace Trail\Trail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'trail:install';

    protected $description = 'Install Trail: publish config and assets, and scaffold the dashboard gate';

    public function handle(): int
    {
        $this->comment('Publishing Trail config...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-config']);

        $this->comment('Publishing Trail assets...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-assets', '--force' => true]);

        $this->comment('Publishing Trail agent skill...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-skill']);

        $this->registerGateProvider();

        $this->info('Trail installed.');
        $this->line('  - Run `php artisan migrate` (migrations are auto-loaded).');
        $this->line('  - Add `use Trail\Trail\Concerns\HasTrail;` to the model you track.');
        $this->line('  - Edit app/Providers/TrailServiceProvider.php to open the dashboard.');

        return self::SUCCESS;
    }

    /**
     * Scaffold app/Providers/TrailServiceProvider.php (the dashboard gate) and register it.
     */
    private function registerGateProvider(): void
    {
        $target = app_path('Providers/TrailServiceProvider.php');

        if (file_exists($target)) {
            $this->comment('app/Providers/TrailServiceProvider.php already exists, skipping.');

            return;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        copy(__DIR__.'/../../resources/stubs/trail-provider.stub', $target);

        ServiceProvider::addProviderToBootstrapFile('App\Providers\TrailServiceProvider');

        $this->comment('Created app/Providers/TrailServiceProvider.php (define the dashboard gate there).');
    }
}
