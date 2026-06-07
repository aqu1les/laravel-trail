<?php

declare(strict_types=1);

namespace Trail\Trail\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'trail:install';

    protected $description = 'Publish Trail config, migrations and assets';

    public function handle(): int
    {
        $this->comment('Publishing Trail config...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-config']);

        $this->comment('Publishing Trail migrations...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-migrations']);

        $this->comment('Publishing Trail assets...');
        $this->callSilently('vendor:publish', ['--tag' => 'trail-assets', '--force' => true]);

        $this->info('Trail installed. Run `php artisan migrate` and protect the dashboard with Trail::auth().');

        return self::SUCCESS;
    }
}
