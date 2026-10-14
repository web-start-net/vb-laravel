<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('wsthanks_thanks')) {
            Capsule::schema()->create('wsthanks_thanks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('nodeid')->index();
                $table->unsignedInteger('userid')->index();
                $table->unsignedInteger('created_at')->default(0);
                $table->unique(['nodeid', 'userid']);
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('wsthanks_thanks');
    }
};
