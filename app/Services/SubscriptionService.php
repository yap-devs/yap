<?php

namespace App\Services;

use App\Models\User;
use App\Models\VmessServer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SubscriptionService
{
    public const FORMAT_CLASH = 'clash';

    public const FORMAT_UNIVERSAL = 'universal';

    public const FORMATS = [
        self::FORMAT_CLASH,
        self::FORMAT_UNIVERSAL,
    ];

    public function content(User $user, string $format): ?string
    {
        return Cache::get($this->cacheKey($user, $format));
    }

    public function userInfo(User $user): string
    {
        return "upload=$user->traffic_uplink; download=$user->traffic_downlink; total=70368744177664; expire=612894867";
    }

    public function warmCache(User $user, ?Collection $servers = null): void
    {
        $servers = $servers ?? $this->serversFor($user);

        foreach (self::FORMATS as $format) {
            Cache::forever($this->cacheKey($user, $format), $this->generate($user, $format, $servers));
        }
    }

    public function forgetCache(User $user): void
    {
        foreach (self::FORMATS as $format) {
            Cache::forget($this->cacheKey($user, $format));
        }
    }

    public function serversFor(User $user, ?Collection $servers = null): Collection
    {
        $servers = $servers ?? VmessServer::where('enabled', true)->with('relays')->get();

        if ($user->is_low_priority) {
            return $servers->filter(fn (VmessServer $server): bool => (bool) $server->for_low_priority)->values();
        }

        return $servers;
    }

    public function cacheKey(User $user, string $format): string
    {
        return "subscription:content:{$user->id}:{$format}";
    }

    private function generate(User $user, string $format, Collection $servers): string
    {
        return match ($format) {
            self::FORMAT_CLASH => (new ClashService($user))->genConf($servers),
            self::FORMAT_UNIVERSAL => $this->universalVmessSubscription($user, $servers),
        };
    }

    private function universalVmessSubscription(User $user, Collection $servers): string
    {
        $proxies = (new ClashService($user))->proxies($servers);
        $links = array_map(fn (array $proxy): string => 'vmess://'.base64_encode(json_encode([
            'v' => '2',
            'ps' => $proxy['name'],
            'add' => $proxy['server'],
            'port' => (string) $proxy['port'],
            'id' => $user->uuid,
            'aid' => '0',
            'scy' => $proxy['cipher'],
            'net' => 'tcp',
            'type' => 'none',
            'host' => '',
            'path' => '',
            'tls' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $proxies);

        return base64_encode(implode("\n", $links)."\n");
    }
}
