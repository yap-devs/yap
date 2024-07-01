<?php

namespace App\Models;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(VmessServer::class);
    }
}
