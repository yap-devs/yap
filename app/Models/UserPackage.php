<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPackage extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_USED = 'used';
    const STATUS_DISABLED = 'disabled';

    protected $with = ['package'];

    protected $fillable = [
        'user_id',
        'package_id',
        'remaining_traffic',
        'priority',
        'status',
        'started_at',
        'ended_at',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
