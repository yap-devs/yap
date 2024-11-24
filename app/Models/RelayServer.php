<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RelayServer extends Model
{
    use SoftDeletes;

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
