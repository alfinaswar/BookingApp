<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kategori', 50)->nullable();
            $table->decimal('harga', 12, 2)->default(0);
            $table->enum('satuan', ['per_night', 'per_person', 'per_person_night', 'per_booking']);
            $table->unsignedInteger('stok')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};