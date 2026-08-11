<?php

declare(strict_types=1);

use VbLaravel\Bridge;

if (!function_exists('vb')) {
    function vb(): Bridge
    {
        static $bridge;

        return $bridge ??= new Bridge();
    }
}
