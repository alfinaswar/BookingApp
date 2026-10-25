<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['roomId', 'checkIn', 'checkOut'], 'bookings_room_stay_index');
            $table->index('Status', 'bookings_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_room_stay_index');
            $table->dropIndex('bookings_status_index');
        });
    }
};
