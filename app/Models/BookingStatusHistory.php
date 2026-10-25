<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'booking_status_histories';

    protected $fillable = [
        'booking_id', 'from_status', 'to_status', 'actor_id', 'note',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}