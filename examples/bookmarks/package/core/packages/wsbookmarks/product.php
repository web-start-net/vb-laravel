<?php

declare(strict_types=1);

class wsbookmarks_Product
{
    public $vbMinVersion = '6.2.2';
    public $vbMaxVersion = '6.9.9';
    public static $AutoInstall = false;
    public $hookClasses = [
        'wsbookmarks_Hooks',
    ];
}
