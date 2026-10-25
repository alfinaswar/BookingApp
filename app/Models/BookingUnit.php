<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingUnit extends Model
{
    use HasFactory;

    protected $table = 'booking_units';

    protected $fillable = [
        'booking_id', 'room_unit_id', 'room_id', 'check_in_date', 'check_out_date',
        'urutan', 'subtotal', 'rate_snapshot',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'urutan' => 'integer',
        'subtotal' => 'float',
        'rate_snapshot' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function unit()
    {
        return $this->belongsTo(RoomUnit::class, 'room_unit_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function nights()
    {
        return $this->hasMany(BookingUnitNight::class, 'booking_unit_id');
    }
}