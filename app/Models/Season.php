<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $table = 'seasons';

    protected $fillable = [
        'nama', 'start_date', 'end_date', 'priority',
        'adjustment_type', 'adjustment_value', 'room_id', 'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'adjustment_value' => 'float',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}