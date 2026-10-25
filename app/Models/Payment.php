<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const METODE_TRANSFER = 'transfer_manual';
    public const METODE_CASH = 'cash';
    public const METODE_MIDTRANS = 'midtrans';

    public const TIPE_DP = 'dp';
    public const TIPE_PELUNASAN = 'pelunasan';
    public const TIPE_FULL = 'full';
    public const TIPE_REFUND = 'refund';

    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING = 'waiting_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'payments';

    protected $fillable = [
        'booking_id', 'kode_pembayaran', 'metode', 'tipe', 'amount', 'status',
        'proof_path', 'gateway_ref', 'raw_payload', 'paid_at',
        'verified_by', 'verified_at', 'rejected_reason',
    ];

    protected $casts = [
        'amount' => 'float',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}