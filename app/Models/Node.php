<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Node extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'agent_token_hash', 'enabled', 'desired_revision', 'applied_revision', 'last_seen_at', 'agent_version', 'core_version', 'core_config'];

    protected $hidden = ['agent_token_hash'];

    protected $attributes = ['enabled' => false, 'desired_revision' => 1, 'applied_revision' => 0];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'desired_revision' => 'integer',
            'applied_revision' => 'integer',
            'last_seen_at' => 'datetime',
            'core_config' => 'object',
        ];
    }

    public function originalIsEquivalent(mixed $key): bool
    {
        if ($key !== 'core_config') {
            return parent::originalIsEquivalent($key);
        }
        if (! array_key_exists($key, $this->original)) {
            return false;
        }
        $current = $this->attributes[$key] ?? null;
        $original = $this->original[$key];
        if ($current === $original) {
            return true;
        }
        if ($current === null || $original === null) {
            return false;
        }

        return json_encode(json_decode($current, false, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR)
            === json_encode(json_decode($original, false, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(NodeRoute::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(TrafficBatch::class);
    }
}
