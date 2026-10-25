<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100)->nullable();
            $table->enum('tipe', ['percent', 'fixed'])->default('percent');
            $table->decimal('nilai', 12, 2)->default(0);
            $table->decimal('min_subtotal', 12, 2)->default(0);
            $table->decimal('max_diskon', 12, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            $table->unsignedInteger('terpakai')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};