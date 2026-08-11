<?php
declare(strict_types=1);
namespace VB\AIThreadAssistant;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\ServiceProvider;
use ReflectionProperty;
final class Provider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void {
        $a = \vB::getDbAssertor(); $p = new ReflectionProperty($a, 'dbconfig'); $c = $p->getValue($a);
        $m = $c['MasterServer'] ?? []; $d = $c['Database'] ?? [];
        $capsule = new Manager($this->app);
        $capsule->addConnection(['driver'=>'mysql','database'=>$d['dbname']??'','host'=>$m['servername']??'127.0.0.1','port'=>$m['port']??3306,'username'=>$m['username']??'','password'=>$m['password']??'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>defined('TABLE_PREFIX')?TABLE_PREFIX:'']);
        $capsule->setAsGlobal(); $capsule->bootEloquent(); $this->app->instance(Manager::class, $capsule);
    }
}
