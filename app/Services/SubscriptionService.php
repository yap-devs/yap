<?php

namespace App\Services;

use App\Models\NodeRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SubscriptionService
{
    public const FORMAT_CLASH = 'clash';

    public const FORMAT_UNIVERSAL = 'universal';

    public const FORMATS = [
        self::FORMAT_CLASH,
        self::FORMAT_UNIVERSAL,
    ];

    public function content(User $user, string $format): string
    {
        throw_unless(in_array($format, self::FORMATS, true), InvalidArgumentException::class, 'Unsupported subscription format.');

        $servers = $this->serversFor($user);
        $signature = $this->signature($user, $format, $servers);
        $cached = $this->cachedContent($user, $format, $signature);

        if ($cached !== null) {
            return $cached;
        }

        return Cache::store(config('subscription.lock_store', 'database'))
            ->lock($this->cacheKey($user, $format).':lock', 60)
            ->block((int) config('subscription.lock_wait_seconds', 10), function () use ($user, $format): string {
                $servers = $this->serversFor($user);
                $signature = $this->signature($user, $format, $servers);
                $cached = $this->cachedContent($user, $format, $signature);

                if ($cached !== null) {
                    return $cached;
                }

                return $this->storeContent($user, $format, $servers, $signature);
            });
    }

    public function userInfo(User $user): string
    {
        return "upload=$user->traffic_uplink; download=$user->traffic_downlink; total=70368744177664; expire=612894867";
    }

    public function warmCache(User $user, ?Collection $servers = null): void
    {
        $servers = $this->serversFor($user, $servers);

        foreach (self::FORMATS as $format) {
            $this->storeContent($user, $format, $servers, $this->signature($user, $format, $servers));
        }
    }

    public function forgetCache(User $user): void
    {
        foreach (self::FORMATS as $format) {
            Cache::store(config('subscription.content_store', 'file'))->forget($this->cacheKey($user, $format));
        }
    }

    public function serversFor(User $user, ?Collection $servers = null): Collection
    {
        $servers ??= NodeRoute::query()
            ->where('enabled', true)
            ->whereHas('node', fn (Builder $query): Builder => $query->where('enabled', true))
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($user->is_low_priority) {
            return $servers->filter(fn (NodeRoute $server): bool => (bool) $server->for_low_priority)->values();
        }

        return $servers;
    }

    public function cacheKey(User $user, string $format): string
    {
        return "subscription:content:{$user->id}:{$format}";
    }

    private function signature(User $user, string $format, Collection $servers): string
    {
        $customizer_path = app_path('ClashYamlCustomizer.php');

        return hash('sha256', serialize([
            'version' => 2,
            'uuid' => $user->uuid,
            'priority' => $user->is_low_priority,
            'proxies' => (new ClashService($user))->proxies($servers),
            'template' => $format === self::FORMAT_CLASH ? hash_file('sha256', resource_path('clash-conf-template.yaml')) : null,
            'customizer' => $format === self::FORMAT_CLASH && is_file($customizer_path) ? hash_file('sha256', $customizer_path) : null,
        ]));
    }

    private function cachedContent(User $user, string $format, string $signature): ?string
    {
        $cached = Cache::store(config('subscription.content_store', 'file'))->get($this->cacheKey($user, $format));

        return is_array($cached) && ($cached['signature'] ?? null) === $signature && is_string($cached['content'] ?? null)
            ? $cached['content']
            : null;
    }

    private function storeContent(User $user, string $format, Collection $servers, string $signature): string
    {
        $content = $this->generate($user, $format, $servers);
        Cache::store(config('subscription.content_store', 'file'))->put(
            $this->cacheKey($user, $format),
            ['signature' => $signature, 'content' => $content],
            max(1, (int) config('subscription.ttl_seconds', 43200)),
        );

        return $content;
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
