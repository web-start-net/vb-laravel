<?php

declare(strict_types=1);

class wsforms_Product
{
    public $vbMinVersion = '6.2.2';
    public $vbMaxVersion = '6.9.9';
    public static $AutoInstall = false;
    public $hookClasses = [
        'wsforms_Hooks',
    ];
}
