<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('room_unit_id')->constrained('room_units')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->json('rate_snapshot')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_units');
    }
};