<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class NodeRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['node_id', 'name', 'server', 'port', 'inbound_tag', 'listen_port', 'rate', 'enabled', 'for_low_priority', 'sort'];

    protected $attributes = ['rate' => '1.00', 'enabled' => false, 'for_low_priority' => false, 'sort' => 0];

    protected static function booted(): void
    {
        static::saving(function (NodeRoute $route): void {
            if ($route->exists && $route->isDirty(['node_id', 'inbound_tag', 'listen_port'])) {
                throw ValidationException::withMessages(['listen_port' => 'Route identity is immutable. Create a new route instead.']);
            }
            if ($route->inbound_tag === 'yap-api') {
                throw ValidationException::withMessages(['inbound_tag' => 'The yap-api inbound is reserved for node management.']);
            }
            if (! preg_match('/\Ayap-[a-zA-Z0-9_-]+\z/', $route->inbound_tag ?? '') || $route->listen_port < 1 || $route->listen_port > 65535 || $route->port < 1 || $route->port > 65535) {
                throw ValidationException::withMessages(['inbound_tag' => 'Use a yap- prefixed inbound and valid ports.']);
            }
            if (bccomp($route->rate, '0', 2) < 0 || bccomp($route->rate, '999999.99', 2) > 0) {
                throw ValidationException::withMessages(['rate' => 'Invalid traffic multiplier.']);
            }
            $siblings = static::where('node_id', $route->node_id)->where('inbound_tag', $route->inbound_tag);
            if ($route->exists) {
                $siblings->whereKeyNot($route->id);
            }
            if ((clone $siblings)->where('for_low_priority', ! $route->for_low_priority)->exists()) {
                throw ValidationException::withMessages(['for_low_priority' => 'Routes sharing a handler must have the same authorization scope.']);
            }
            $route->validatePortRange($route->enabled && ! $route->trashed());
        });
        static::deleting(function (NodeRoute $route): void {
            if ($route->enabled && ! $route->trashed()) {
                $route->validatePortRange(false);
            }
        });
    }

    private function validatePortRange(bool $include_route): void
    {
        $siblings = static::where('node_id', $this->node_id)
            ->where('inbound_tag', $this->inbound_tag)
            ->where('enabled', true);
        if ($this->exists) {
            $siblings->whereKeyNot($this->id);
        }
        $ports = $siblings->pluck('listen_port');
        if ($include_route) {
            $ports->push($this->listen_port);
        }
        if ($ports->isNotEmpty() && $ports->max() - $ports->min() + 1 !== $ports->count()) {
            throw ValidationException::withMessages(['enabled' => 'Enabled ports sharing a handler must be contiguous. Disable edge routes first or use a separate handler.']);
        }
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'enabled' => 'boolean',
            'for_low_priority' => 'boolean',
            'port' => 'integer',
            'listen_port' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
