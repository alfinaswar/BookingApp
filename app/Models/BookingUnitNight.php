<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingUnitNight extends Model
{
    use HasFactory;

    public const STATUS_HELD = 'held';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_RELEASED = 'released';

    protected $table = 'booking_unit_nights';

    protected $fillable = [
        'booking_id', 'booking_unit_id', 'room_unit_id', 'stay_date', 'rate', 'status',
    ];

    protected $casts = [
        'stay_date' => 'date',
        'rate' => 'float',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function bookingUnit()
    {
        return $this->belongsTo(BookingUnit::class, 'booking_unit_id');
    }

    public function unit()
    {
        return $this->belongsTo(RoomUnit::class, 'room_unit_id');
    }

    public function scopeOccupied($query)
    {
        return $query->whereIn('status', [self::STATUS_HELD, self::STATUS_BOOKED]);
    }
}