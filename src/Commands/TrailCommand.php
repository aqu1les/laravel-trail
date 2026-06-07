<?php

namespace Trail\Trail\Commands;

use Illuminate\Console\Command;

class TrailCommand extends Command
{
    public $signature = 'laravel-trail';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
