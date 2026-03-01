<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venuebookings_operating_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('resource_id');
            $table->string('scheduling_mode', 20)->default('time_slots');
            $table->unsignedInteger('slot_duration_minutes')->default(60);
            $table->unsignedInteger('min_consecutive_slots')->default(1);
            $table->unsignedInteger('max_consecutive_slots')->default(4);
            $table->json('day_schedules');
            $table->timestamps();

            $table->unique('resource_id');
            $table->foreign('resource_id')
                ->references('id')
                ->on('venuebookings_resources')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venuebookings_operating_schedules');
    }
};
