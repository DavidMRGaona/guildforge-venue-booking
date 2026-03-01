<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venuebookings_bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('resource_id');
            $table->uuid('user_id');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('pending');
            $table->uuid('event_id')->nullable();
            $table->string('game_table_id')->nullable();
            $table->string('tournament_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->json('field_values')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('resource_id')
                ->references('id')
                ->on('venuebookings_resources')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('set null');

            $table->index(['resource_id', 'date']);
            $table->index('user_id');
            $table->index('status');
            $table->index(['date', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venuebookings_bookings');
    }
};
