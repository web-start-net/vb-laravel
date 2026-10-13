<?php

declare(strict_types=1);

namespace VbLaravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Hook
{
    public function __construct(
        public string $name,
        public int $order = 10
    ) {
    }
}
