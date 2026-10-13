<?php

declare(strict_types=1);

namespace VbLaravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Can
{
    public function __construct(
        public string $permission,
        public string $group = 'genericpermissions'
    ) {
    }
}
