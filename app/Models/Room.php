<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'roomtype', 'nama', 'deskripsi', 'qty', 'kapasitas_base', 'kapasitas_max', 'min_stay',
        'checkout', 'tarifWd', 'tarifWe', 'base_rate_weekday', 'base_rate_weekend',
        'Fasilitas', 'status', 'imgPreview', 'is_active', 'sort_order',
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'Fasilitas' => 'json',
        'kapasitas_base' => 'integer',
        'kapasitas_max' => 'integer',
        'min_stay' => 'integer',
        'base_rate_weekday' => 'float',
        'base_rate_weekend' => 'float',
        'tarifWd' => 'float',
        'tarifWe' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roomtypes()
    {
        return $this->hasOne(Roomtype::class, 'id', 'roomtype');
    }

    public function fotoroom()
    {
        return $this->hasMany(roomDetail::class, 'idRoom', 'id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'roomId', 'id');
    }

    public function units()
    {
        return $this->hasMany(RoomUnit::class, 'room_id');
    }

    public function seasons()
    {
        return $this->hasMany(Season::class, 'room_id');
    }

    public function rateOverrides()
    {
        return $this->hasMany(RateOverride::class, 'room_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Tarif dasar, dengan fallback ke kolom lama bila kolom baru belum diisi.
     */
    public function baseRate(bool $weekend): float
    {
        $rate = $weekend ? $this->base_rate_weekend : $this->base_rate_weekday;

        if ($rate > 0) {
            return (float) $rate;
        }

        return (float) ($weekend ? $this->tarifWe : $this->tarifWd);
    }

    public function namaTipe(): string
    {
        return $this->roomtypes->nama ?? 'Tanpa Tipe';
    }
}
