<?php

declare(strict_types=1);

namespace VbLaravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Api
{
    public function __construct(
        public string $controller,
        public ?string $action = null,
        public bool $write = false,
        public bool $guest = false
    ) {
    }
}
