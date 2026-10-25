<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addon extends Model
{
    use HasFactory, SoftDeletes;

    public const UNIT_PER_NIGHT = 'per_night';
    public const UNIT_PER_PERSON = 'per_person';
    public const UNIT_PER_PERSON_NIGHT = 'per_person_night';
    public const UNIT_PER_BOOKING = 'per_booking';

    protected $table = 'addons';

    protected $fillable = [
        'nama', 'kategori', 'harga', 'satuan', 'stok', 'is_active', 'sort_order', 'deskripsi',
    ];

    protected $casts = [
        'harga' => 'float',
        'stok' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Harga add-on untuk satu booking.
     */
    public function priceFor(int $nights, int $guests, int $qty = 1): float
    {
        $multiplier = match ($this->satuan) {
            self::UNIT_PER_NIGHT => max(1, $nights),
            self::UNIT_PER_PERSON => max(1, $guests),
            self::UNIT_PER_PERSON_NIGHT => max(1, $nights) * max(1, $guests),
            default => 1,
        };

        return round($this->harga * $multiplier * max(1, $qty), 2);
    }
}