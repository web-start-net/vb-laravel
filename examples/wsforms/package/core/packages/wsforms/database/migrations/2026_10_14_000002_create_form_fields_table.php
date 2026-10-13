<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        Capsule::schema()->create('ws_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->string('label', 150);
            $table->string('field_type', 32)->default('text');
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('display_order')->default(10);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('ws_form_fields');
    }
};
