<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserStat extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'server_id',
        'traffic_downlink',
        'traffic_uplink',
    ];

    protected $appends = ['date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(VmessServer::class);
    }

    protected function date(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return Carbon::parse($attributes['created_at'])->format('m/d');
            },
        );
    }
}
