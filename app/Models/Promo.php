<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $table = 'promos';

    protected $fillable = [
        'kode', 'nama', 'tipe', 'nilai', 'min_subtotal', 'max_diskon',
        'valid_from', 'valid_to', 'kuota', 'terpakai', 'is_active',
    ];

    protected $casts = [
        'nilai' => 'float',
        'min_subtotal' => 'float',
        'max_diskon' => 'float',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'kuota' => 'integer',
        'terpakai' => 'integer',
        'is_active' => 'boolean',
    ];

    public function isUsable(float $subtotal, ?string $date = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $date = $date ? \Carbon\Carbon::parse($date) : now();

        if ($this->valid_from && $date->lt($this->valid_from->startOfDay())) {
            return false;
        }

        if ($this->valid_to && $date->gt($this->valid_to->endOfDay())) {
            return false;
        }

        if ($this->kuota !== null && $this->terpakai >= $this->kuota) {
            return false;
        }

        return $subtotal >= $this->min_subtotal;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->tipe === self::TYPE_PERCENT
            ? $subtotal * ($this->nilai / 100)
            : $this->nilai;

        if ($this->max_diskon !== null) {
            $discount = min($discount, $this->max_diskon);
        }

        return round(min($discount, $subtotal), 2);
    }
}