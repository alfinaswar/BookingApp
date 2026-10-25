<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_unit_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('booking_unit_id')->constrained('booking_units')->cascadeOnDelete();
            $table->foreignId('room_unit_id')->constrained('room_units')->restrictOnDelete();
            $table->date('stay_date');
            $table->decimal('rate', 12, 2)->default(0);
            $table->enum('status', ['held', 'booked', 'released'])->default('held');
            $table->timestamps();

            // Jaminan anti-overbooking di level database.
            $table->unique(['room_unit_id', 'stay_date'], 'booking_unit_nights_unit_date_unique');
            $table->index('stay_date', 'booking_unit_nights_stay_date_index');
            $table->index(['booking_id', 'status'], 'booking_unit_nights_booking_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_nights');
    }
};