<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->enum('adjustment_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('adjustment_value', 12, 2)->default(0);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date', 'is_active'], 'seasons_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};