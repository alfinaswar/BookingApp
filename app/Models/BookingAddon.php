<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingAddon extends Model
{
    use HasFactory;

    protected $table = 'booking_addons';

    protected $fillable = [
        'booking_id', 'addon_id', 'nama_snapshot', 'satuan', 'qty', 'unit_price', 'subtotal',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }
}