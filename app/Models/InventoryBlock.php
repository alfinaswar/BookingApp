<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryBlock extends Model
{
    use HasFactory;

    protected $table = 'inventory_blocks';

    protected $fillable = [
        'room_unit_id', 'start_date', 'end_date', 'reason', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(RoomUnit::class, 'room_unit_id');
    }
}