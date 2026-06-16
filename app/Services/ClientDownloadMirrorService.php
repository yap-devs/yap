<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ClientDownloadMirrorService
{
    public function sync(bool $dry_run = false): array
    {
        $releases = [];
        $synced_assets = [];

        foreach ($this->targetsByRepository() as $repo => $targets) {
            $release = $this->fetchLatestRelease($repo);
            $releases[$repo] = $release;

            foreach ($targets as $key => $target) {
                $asset = $this->findAsset($release['assets'] ?? [], $target);

                if ($asset === null) {
                    throw new RuntimeException('No matching asset found for '.$key.' in '.$repo.' '.$release['tag_name']);
                }

                $synced_assets[$key] = $this->syncAsset($key, $target, $release, $asset, $dry_run);
            }
        }

        $manifest = [
            'synced_at' => now()->toIso8601String(),
            'assets' => $synced_assets,
        ];

        if (! $dry_run) {
            $this->disk()->put($this->manifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $manifest;
    }

    public function findAsset(array $assets, array $target): ?array
    {
        foreach ($assets as $asset) {
            $name = (string) ($asset['name'] ?? '');

            foreach ($target['patterns'] as $pattern) {
                if (preg_match($pattern, $name) === 1) {
                    return $asset;
                }
            }
        }

        return null;
    }

    public function targets(): array
    {
        $targets = config('services.client_downloads.targets', []);

        if (! is_array($targets)) {
            return [];
        }

        return $targets;
    }

    public function downloads(): array
    {
        $downloads = [];

        foreach ($this->targets() as $key => $target) {
            if (! $this->isValidTarget($key, $target)) {
                continue;
            }

            $downloads[] = [
                'key' => $key,
                'label' => $target['label'],
                'repo' => $target['repo'],
                'url' => route('customer.service.download', ['client' => $key]),
                'github_url' => 'https://github.com/'.$target['repo'].'/releases/latest',
            ];
        }

        return $downloads;
    }

    public function githubReleaseUrl(string $key): string
    {
        $target = $this->targets()[$key] ?? null;
        if ($target === null) {
            throw new InvalidArgumentException('Unknown client download: '.$key);
        }

        $this->validateTarget($key, $target);

        return 'https://github.com/'.$target['repo'].'/releases/latest';
    }

    public function temporaryDownloadUrl(string $key): string
    {
        $target = $this->targets()[$key] ?? null;
        if ($target === null) {
            throw new InvalidArgumentException('Unknown client download: '.$key);
        }

        $this->validateTarget($key, $target);

        $asset = $this->mirroredAsset($key);
        if ($asset === null) {
            throw new RuntimeException('Mirrored client download is unavailable: '.$key);
        }

        return $this->disk()->temporaryUrl(
            $asset['versioned_path'],
            now()->addMinutes((int) config('services.client_downloads.signed_url_ttl_minutes', 10)),
            [
                'ResponseContentType' => $this->contentType((string) ($asset['source_name'] ?? $target['latest_name'])),
                'ResponseContentDisposition' => 'attachment; filename="'.$target['latest_name'].'"',
            ],
        );
    }

    public function hasMirroredDownload(string $key): bool
    {
        $target = $this->targets()[$key] ?? null;
        if ($target === null) {
            return false;
        }

        $this->validateTarget($key, $target);

        return $this->mirroredAsset($key) !== null;
    }

    public function mirroredAsset(string $key): ?array
    {
        try {
            $asset = $this->manifestAssets()[$key] ?? null;
            if (! is_array($asset) || ! is_string($asset['versioned_path'] ?? null)) {
                return null;
            }

            return $this->disk()->exists($asset['versioned_path']) ? $asset : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function syncAsset(string $key, array $target, array $release, array $asset, bool $dry_run): array
    {
        $asset_name = (string) $asset['name'];
        $version = ltrim((string) $release['tag_name'], 'v');
        $versioned_path = $this->path($key.'/'.$version.'/'.$asset_name);
        $latest_path = $this->path($key.'/'.$target['latest_name']);
        $digest = $this->normalizeSha256($asset['digest'] ?? null);

        if (! $dry_run && ! $this->disk()->exists($versioned_path)) {
            $temporary_path = $this->downloadAsset((string) $asset['browser_download_url']);
            $actual_digest = hash_file('sha256', $temporary_path);

            if ($digest !== null && ! hash_equals($digest, $actual_digest)) {
                @unlink($temporary_path);

                throw new RuntimeException('Checksum mismatch for '.$asset_name);
            }

            $stream = fopen($temporary_path, 'rb');
            if ($stream === false) {
                @unlink($temporary_path);

                throw new RuntimeException('Unable to open downloaded asset: '.$temporary_path);
            }

            try {
                $this->disk()->put($versioned_path, $stream, $this->uploadOptions($asset_name));
                rewind($stream);
                $this->disk()->put($latest_path, $stream, $this->uploadOptions($asset_name));
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                @unlink($temporary_path);
            }
        } elseif (! $dry_run) {
            $stream = $this->disk()->readStream($versioned_path);
            if ($stream === false) {
                throw new RuntimeException('Unable to read mirrored asset: '.$versioned_path);
            }

            $this->disk()->put($latest_path, $stream, $this->uploadOptions($asset_name));

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'label' => $target['label'],
            'repo' => $target['repo'],
            'version' => $version,
            'source_name' => $asset_name,
            'size' => $asset['size'] ?? null,
            'sha256' => $digest,
            'versioned_path' => $versioned_path,
            'latest_path' => $latest_path,
            'source_url' => $asset['browser_download_url'] ?? null,
        ];
    }

    private function fetchLatestRelease(string $repo): array
    {
        $response = $this->githubClient()
            ->get('https://api.github.com/repos/'.$repo.'/releases/latest')
            ->throw();

        return $response->json();
    }

    private function downloadAsset(string $url): string
    {
        $temporary_path = tempnam(sys_get_temp_dir(), 'yap-client-download-');
        if ($temporary_path === false) {
            throw new RuntimeException('Unable to create temporary download file.');
        }

        try {
            $this->githubClient()
                ->timeout(300)
                ->withOptions(['sink' => $temporary_path])
                ->get($url)
                ->throw();
        } catch (Throwable $e) {
            @unlink($temporary_path);

            throw $e;
        }

        return $temporary_path;
    }

    private function githubClient(): PendingRequest
    {
        $client = Http::acceptJson()
            ->withUserAgent('YAP client download mirror')
            ->retry([1000, 3000, 5000])
            ->connectTimeout(20)
            ->timeout(60);

        $token = config('services.client_downloads.github_token');
        if (is_string($token) && $token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function targetsByRepository(): array
    {
        $targets = [];

        foreach ($this->targets() as $key => $target) {
            $this->validateTarget($key, $target);

            $targets[$target['repo']][$key] = $target;
        }

        return $targets;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('services.client_downloads.disk'));
    }

    private function path(string $path): string
    {
        return trim(config('services.client_downloads.prefix'), '/').'/'.ltrim($path, '/');
    }

    private function manifestPath(): string
    {
        return $this->path('manifest.json');
    }

    private function manifestAssets(): array
    {
        try {
            if (! $this->disk()->exists($this->manifestPath())) {
                return [];
            }

            $manifest = json_decode($this->disk()->get($this->manifestPath()), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($manifest['assets'] ?? null) ? $manifest['assets'] : [];
    }

    private function normalizeSha256(mixed $digest): ?string
    {
        if (! is_string($digest) || ! Str::startsWith($digest, 'sha256:')) {
            return null;
        }

        return Str::after($digest, 'sha256:');
    }

    private function uploadOptions(string $asset_name): array
    {
        return [
            'visibility' => 'private',
            'ContentType' => $this->contentType($asset_name),
        ];
    }

    private function validateTarget(string $key, array $target): void
    {
        foreach (['repo', 'label', 'latest_name'] as $field) {
            if (! is_string($target[$field] ?? null) || $target[$field] === '') {
                throw new InvalidArgumentException('Invalid client download target '.$key.': missing '.$field);
            }
        }

        if (! is_array($target['patterns'] ?? null) || $target['patterns'] === []) {
            throw new InvalidArgumentException('Invalid client download target '.$key.': missing patterns');
        }
    }

    private function isValidTarget(string $key, mixed $target): bool
    {
        if (! is_array($target)) {
            return false;
        }

        try {
            $this->validateTarget($key, $target);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function contentType(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'apk' => 'application/vnd.android.package-archive',
            'dmg' => 'application/x-apple-diskimage',
            'exe' => 'application/vnd.microsoft.portable-executable',
            default => 'application/octet-stream',
        };
    }
}
