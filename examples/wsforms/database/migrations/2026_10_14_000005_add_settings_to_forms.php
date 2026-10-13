<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        Capsule::schema()->table('ws_forms', function (Blueprint $table) {
            $table->string('custom_code', 50)->nullable();
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('ws_forms', function (Blueprint $table) {
            $table->dropColumn('custom_code');
        });
    }
};
