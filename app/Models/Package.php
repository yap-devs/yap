<?php

namespace App\Models;

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

    public function getOriginalPriceAttribute()
    {
        return $this->traffic_limit / 1024 / 1024 / 1024 * config('yap.unit_price');
    }
}
