<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Sub2apiService
{
    private const CACHE_ACCESS_TOKEN_KEY = 'sub2api_access_token';

    public function isEnabled(): bool
    {
        return (bool) config('services.sub2api.enabled');
    }

    public function getCreateThreshold(): float
    {
        return (float) config('services.sub2api.min_balance_to_create_key');
    }

    public function getKeepActiveThreshold(): float
    {
        return (float) config('services.sub2api.min_balance_to_keep_active');
    }

    public function generateCustomKey(User $user): string
    {
        return config('services.sub2api.key_prefix').str_replace('-', '', $user->uuid);
    }

    public function generateKeyName(User $user): string
    {
        $email_name = strstr($user->email, '@', true) ?: $user->email;

        return 'YAP-'.$email_name.'-'.substr(str_replace('-', '', $user->uuid), 0, 8);
    }

    public function createKey(User $user): array
    {
        return $this->request('post', '/api/v1/keys', [
            'name' => $this->generateKeyName($user),
            'group_id' => config('services.sub2api.default_group_id'),
            'custom_key' => $this->generateCustomKey($user),
        ]);
    }

    public function deleteKey(int $key_id): array
    {
        return $this->request('delete', '/api/v1/keys/'.$key_id);
    }

    public function updateKeyStatus(int $key_id, string $status): array
    {
        return $this->request('put', '/api/v1/keys/'.$key_id, [
            'status' => $status,
        ]);
    }

    public function listUsage(int $key_id, int $last_usage_id = 0): array
    {
        $page = 1;
        $new_items = [];

        do {
            $data = $this->request('get', '/api/v1/usage', query: [
                'api_key_id' => $key_id,
                'page' => $page,
                'page_size' => 100,
                'sort_by' => 'id',
                'sort_order' => 'desc',
            ]);

            $items = $data['items'] ?? [];
            if ($items === []) {
                break;
            }

            $has_newer_items = false;
            foreach ($items as $item) {
                if (($item['id'] ?? 0) > $last_usage_id) {
                    $new_items[] = $item;
                    $has_newer_items = true;
                }
            }

            $page++;
            $total_pages = (int) ($data['pages'] ?? 1);
        } while ($has_newer_items && $page <= $total_pages);

        usort($new_items, fn (array $a, array $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));

        return $new_items;
    }

    private function request(string $method, string $uri, array $payload = [], array $query = [], bool $retry = true): array
    {
        $options = ['query' => $query];
        if ($payload !== [] && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $options['json'] = $payload;
        }

        $response = $this->client($this->getAccessToken())->send(strtoupper($method), $uri, $options);

        if ($response->unauthorized() && $retry) {
            Cache::forget(self::CACHE_ACCESS_TOKEN_KEY);

            return $this->request($method, $uri, $payload, $query, false);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Sub2API request failed: '.$response->body());
        }

        $decoded = $response->json();
        if (($decoded['code'] ?? 1) !== 0) {
            throw new RuntimeException('Sub2API request failed: '.($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded['data'] ?? [];
    }

    private function getAccessToken(): string
    {
        $token = Cache::get(self::CACHE_ACCESS_TOKEN_KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = $this->client()->post('/api/v1/auth/login', [
            'email' => config('services.sub2api.admin_email'),
            'password' => config('services.sub2api.admin_password'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Sub2API login failed: '.$response->body());
        }

        $decoded = $response->json();
        if (($decoded['code'] ?? 1) !== 0 || empty($decoded['data']['access_token'])) {
            throw new RuntimeException('Sub2API login failed: '.($decoded['message'] ?? 'Unknown error'));
        }

        $token = $decoded['data']['access_token'];
        $ttl = max(((int) ($decoded['data']['expires_in'] ?? 3600)) - 300, 60);
        Cache::put(self::CACHE_ACCESS_TOKEN_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function client(?string $access_token = null): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->baseUrl(rtrim((string) config('services.sub2api.base_url'), '/'))
            ->timeout(30);

        if ($access_token) {
            $client = $client->withToken($access_token);
        }

        return $client;
    }
}
