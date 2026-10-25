<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_unit_id')->constrained('room_units')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date'], 'inventory_blocks_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_blocks');
    }
};