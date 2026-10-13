<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        Capsule::schema()->create('ws_form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('field_id')->index();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('ws_form_submission_values');
    }
};
