<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('kode_pembayaran', 50)->unique();
            $table->enum('metode', ['transfer_manual', 'cash', 'midtrans'])->default('transfer_manual');
            $table->enum('tipe', ['dp', 'pelunasan', 'full', 'refund'])->default('dp');
            $table->decimal('amount', 14, 2)->default(0);
            $table->enum('status', [
                'pending', 'waiting_verification', 'verified', 'rejected', 'expired', 'refunded',
            ])->default('pending');
            $table->string('proof_path')->nullable();
            $table->string('gateway_ref', 100)->nullable()->unique();
            $table->json('raw_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status'], 'payments_booking_status_index');
            $table->index(['status', 'created_at'], 'payments_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};