<?php

declare(strict_types=1);

require_once DIR . '/packages/vblaravel/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    if (strncasecmp($class, 'wsbookmarks\\_Generated\\', strlen('wsbookmarks\\_Generated\\')) === 0) {
        $rel = substr($class, strlen('wsbookmarks\\_Generated\\'));
        $file = __DIR__ . '/src/_Generated/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
    $map = array (
  'WsBookmarks\\' => 'src/',
);
    foreach ($map as $prefix => $path) {
        if (strncasecmp($class, $prefix, strlen($prefix)) === 0) {
            $rel = substr($class, strlen($prefix));
            $file = __DIR__ . '/' . rtrim($path, '/\\') . '/' . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});

class wsbookmarks_Product
{
    public $vbMinVersion = '6.2.2';
    public $vbMaxVersion = '6.9.9';
    public static $AutoInstall = false;
    public $hookClasses = [
        'wsbookmarks_Hooks',
    ];
}
