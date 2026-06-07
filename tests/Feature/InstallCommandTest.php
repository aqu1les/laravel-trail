<?php

declare(strict_types=1);

it('runs the install command', function () {
    $this->artisan('trail:install')->assertSuccessful();
});
