<?php

declare(strict_types=1);

namespace WsThanks;

use Illuminate\Support\ServiceProvider;

class ThanksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind add-on services or dependencies into the Illuminate container
    }

    public function boot(): void
    {
        // Boot services when Illuminate is lazily initialized
    }
}
