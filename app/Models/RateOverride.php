<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateOverride extends Model
{
    use HasFactory;

    protected $table = 'rate_overrides';

    protected $fillable = ['room_id', 'date', 'rate', 'min_stay', 'closed'];

    protected $casts = [
        'date' => 'date',
        'rate' => 'float',
        'min_stay' => 'integer',
        'closed' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}