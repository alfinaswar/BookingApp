<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomUnit extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'room_units';

    protected $fillable = [
        'room_id', 'kode', 'nama', 'status', 'lantai', 'catatan',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function nights()
    {
        return $this->hasMany(BookingUnitNight::class, 'room_unit_id');
    }

    public function blocks()
    {
        return $this->hasMany(InventoryBlock::class, 'room_unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function label(): string
    {
        return $this->nama ?: $this->kode;
    }
}