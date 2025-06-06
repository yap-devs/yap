<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VmessServer extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'enabled' => 'boolean',
        'rate' => 'float',
    ];

    protected function rate(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (int)$value == $value ? (int)$value : $value,
        );
    }

    protected function server(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ?? '',
        );
    }

    public function stats()
    {
        return $this->hasMany(UserStat::class);
    }

    public function relays()
    {
        return $this->hasMany(RelayServer::class);
    }
}
