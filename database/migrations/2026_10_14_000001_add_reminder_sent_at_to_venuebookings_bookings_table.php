<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venuebookings_bookings', function (Blueprint $table): void {
            $table->dateTime('reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venuebookings_bookings', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
