<?php

declare(strict_types=1);

namespace WsForms;

use Illuminate\Container\Container;

class FormsServiceProvider
{
    public function __construct(protected Container $container)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
