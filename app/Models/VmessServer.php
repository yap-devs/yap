<?php

namespace App\Models;

use App\Models\Scopes\EnabledScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([EnabledScope::class])]
class VmessServer extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
