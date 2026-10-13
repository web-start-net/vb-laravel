<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        Capsule::schema()->create('ws_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedInteger('userid')->index();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('ws_form_submissions');
    }
};
