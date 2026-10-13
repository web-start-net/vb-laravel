<?php

declare(strict_types=1);

namespace WsBookmarks;

use Illuminate\Container\Container;

class BookmarksServiceProvider
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
