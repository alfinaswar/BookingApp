<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_NO_SHOW = 'no_show';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_FAILED = 'failed';

    public const SOURCE_ONLINE = 'online';
    public const SOURCE_WALKIN = 'walkin';
    public const SOURCE_PHONE = 'phone';
    public const SOURCE_OTA = 'ota';

    /** Status yang dianggap masih memesan unit. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    protected $fillable = [
        'kode_booking', 'roomId', 'userId', 'NamaBooking', 'Email', 'JenisIdentitas',
        'NoIdentitas', 'Gender', 'hp', 'checkIn', 'checkOut',
        'check_in_date', 'check_out_date', 'malam', 'jumlah_unit',
        'jumlahTamu', 'jumlah_dewasa', 'jumlah_anak',
        'status', 'payment_status', 'source',
        'subtotal', 'discount_total', 'addon_total', 'tax_total', 'service_total',
        'grand_total', 'dp_amount', 'paid_total', 'refunded_total', 'balance_due', 'promo_id',
        'expires_at', 'confirmed_at', 'checked_in_at', 'checked_out_at',
        'cancelled_at', 'cancel_reason', 'refund_reason', 'created_by', 'isOnline',
    ];

    protected $casts = [
        'Gender' => 'string',
        'checkIn' => 'datetime',
        'checkOut' => 'datetime',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'malam' => 'integer',
        'jumlah_unit' => 'integer',
        'jumlah_dewasa' => 'integer',
        'jumlah_anak' => 'integer',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'addon_total' => 'float',
        'tax_total' => 'float',
        'service_total' => 'float',
        'grand_total' => 'float',
        'dp_amount' => 'float',
        'paid_total' => 'float',
        'refunded_total' => 'float',
        'balance_due' => 'float',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function roomtypes()
    {
        // Dipertahankan demi kompatibilitas kode lama; relasi sebenarnya ada di units().
        return $this->hasOne(Room::class, 'id', 'roomId');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'roomId');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'userId');
    }

    public function units()
    {
        return $this->hasMany(BookingUnit::class, 'booking_id')->orderBy('urutan');
    }

    public function nights()
    {
        return $this->hasMany(BookingUnitNight::class, 'booking_id');
    }

    public function addons()
    {
        return $this->hasMany(BookingAddon::class, 'booking_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    public function histories()
    {
        return $this->hasMany(BookingStatusHistory::class, 'booking_id')->latest();
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOverlapping($query, string $checkIn, string $checkOut)
    {
        return $query->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isHoldExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu DP',
            self::STATUS_CONFIRMED => 'Terkonfirmasi',
            self::STATUS_CHECKED_IN => 'Check-in',
            self::STATUS_CHECKED_OUT => 'Check-out',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            self::STATUS_NO_SHOW => 'No Show',
            default => ucfirst((string) $this->status),
        };
    }

    public function labelPayment(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_UNPAID => 'Belum Bayar',
            self::PAYMENT_PARTIAL => 'DP Terbayar',
            self::PAYMENT_PAID => 'Lunas',
            self::PAYMENT_REFUNDED => 'Refund',
            self::PAYMENT_FAILED => 'Gagal',
            default => ucfirst((string) $this->payment_status),
        };
    }

    /**
     * Batas maksimal DP yang dapat dikembalikan saat pembatalan.
     *
     * Bila setting cancel_dp_refund = 1, DP dikembalikan penuh.
     * Bila 0, DP hangus (non-refundable). Pelunasan tidak pernah otomatis
     * dikembalikan — itu keputusan manual admin.
     */
    public function refundableAmount(): float
    {
        $refundDp = \App\Models\Setting::getInt('cancel_dp_refund', 0) === 1;

        if (! $refundDp) {
            return 0.0;
        }

        $net = max(0, (float) $this->paid_total - (float) $this->refunded_total);

        return round(min($net, (float) $this->dp_amount), 2);
    }
}
