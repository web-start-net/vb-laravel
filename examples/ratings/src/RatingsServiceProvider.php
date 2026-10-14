<?php

declare(strict_types=1);

namespace WsRatings;

use Illuminate\Container\Container;

class RatingsServiceProvider
{
    public function __construct(protected ?Container $container = null)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
