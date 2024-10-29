<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const STATUS_HIDDEN = 'hidden';
    const STATUS_DISABLED = 'disabled';

    protected $appends = ['original_price'];

    protected function originalPrice(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['traffic_limit'] / 1024 / 1024 / 1024 * config('yap.unit_price');
            }
        );
    }
}
