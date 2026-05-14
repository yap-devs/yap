<?php

namespace App\Services;

use JsonException;
use RuntimeException;

class V2rayConfigEditor
{
    public function decode(string $json): array
    {
        $config = $this->decodeJsonObject($json, 'Invalid V2Ray JSON');

        $config['inbounds'] ??= [];
        if (! is_array($config['inbounds'])) {
            throw new RuntimeException('Invalid V2Ray JSON: inbounds must be an array.');
        }

        return $config;
    }

    public function encode(array $config): string
    {
        try {
            return json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode V2Ray JSON: '.$e->getMessage(), previous: $e);
        }
    }

    public function hash(string $json): string
    {
        return hash('sha256', $json);
    }

    public function inboundsToForm(array $config, int $current_port): array
    {
        $result = [];

        foreach (array_values($config['inbounds'] ?? []) as $index => $inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            $result[] = $this->inboundToForm($inbound, $index, $current_port);
        }

        return $result;
    }

    public function applyInboundForm(array $config, array $inbounds): array
    {
        $config['inbounds'] = [];

        foreach (array_values($inbounds) as $index => $inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            $config['inbounds'][] = $this->inboundFromForm($inbound, $index);
        }

        return $config;
    }

    public function updateVmessClientsByPort(array $config, int $port, array $users): array
    {
        $found = false;
        $changed = false;
        $users = array_values($users);

        foreach ($config['inbounds'] as &$inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            if (! $this->isCurrentVmessInbound($inbound, $port)) {
                continue;
            }

            $found = true;
            $inbound['settings'] = is_array($inbound['settings'] ?? null) ? $inbound['settings'] : [];
            $current_users = $inbound['settings']['clients'] ?? [];
            if ($this->clientsAreSame($current_users, $users)) {
                continue;
            }

            $inbound['settings']['clients'] = $users;
            $changed = true;
        }
        unset($inbound);

        return [$config, $found, $changed];
    }

    public function prepareTemplateVmessInboundsForPorts(array $config, array $ports): array
    {
        $ports = array_values(array_unique(array_map('intval', $ports)));
        if ($ports === []) {
            return [$config, false];
        }

        $config['inbounds'] = is_array($config['inbounds'] ?? null) ? $config['inbounds'] : [];
        $template_index = $this->firstVmessInboundIndex($config['inbounds']);
        $template = $template_index === null ? $this->defaultVmessInbound() : $config['inbounds'][$template_index];
        $changed = false;

        foreach ($ports as $index => $port) {
            if ($this->hasVmessInboundForPort($config['inbounds'], $port)) {
                continue;
            }

            $inbound = $template;
            $inbound['port'] = $port;
            $inbound['protocol'] = 'vmess';
            $inbound['settings'] = is_array($inbound['settings'] ?? null) ? $inbound['settings'] : [];
            $inbound['settings']['clients'] = [];

            if ($index > 0 || $template_index === null) {
                $inbound['tag'] = 'main-inbound-'.$port;
                $config['inbounds'][] = $inbound;
            } else {
                $config['inbounds'][$template_index] = $inbound;
            }

            $changed = true;
        }

        return [$config, $changed];
    }

    public function isCurrentVmessInbound(array $inbound, int $current_port): bool
    {
        return ($inbound['protocol'] ?? null) === 'vmess'
            && (int) ($inbound['port'] ?? 0) === $current_port;
    }

    private function inboundToForm(array $inbound, int $index, int $current_port): array
    {
        $clients = [];
        if (($inbound['protocol'] ?? null) === 'vmess') {
            $clients = $this->clientsToForm($inbound['settings']['clients'] ?? []);
        }

        return [
            'original_index' => $index,
            'is_current_entry' => $this->isCurrentVmessInbound($inbound, $current_port),
            'tag' => (string) ($inbound['tag'] ?? ''),
            'listen' => (string) ($inbound['listen'] ?? ''),
            'port' => $inbound['port'] ?? null,
            'protocol' => (string) ($inbound['protocol'] ?? 'vmess'),
            'clients_count' => count($clients),
            'clients' => $clients,
            'raw_json' => $this->encode($inbound),
        ];
    }

    private function inboundFromForm(array $inbound, int $index): array
    {
        $raw_json = trim((string) ($inbound['raw_json'] ?? ''));
        $decoded = $raw_json === '' ? [] : $this->decodeInboundJson($raw_json, $index);

        $tag = trim((string) ($inbound['tag'] ?? ''));
        if ($tag === '') {
            unset($decoded['tag']);
        } else {
            $decoded['tag'] = $tag;
        }

        $listen = trim((string) ($inbound['listen'] ?? ''));
        if ($listen === '') {
            unset($decoded['listen']);
        } else {
            $decoded['listen'] = $listen;
        }

        $decoded['port'] = (int) ($inbound['port'] ?? 0);
        $decoded['protocol'] = trim((string) ($inbound['protocol'] ?? 'vmess')) ?: 'vmess';

        if ($decoded['protocol'] === 'vmess') {
            $decoded['settings'] = is_array($decoded['settings'] ?? null) ? $decoded['settings'] : [];
            $decoded['settings']['clients'] = $this->clientsFromForm($inbound['clients'] ?? []);
        }

        return $decoded;
    }

    private function decodeInboundJson(string $json, int $index): array
    {
        $decoded = $this->decodeJsonObject($json, 'Inbound #'.($index + 1).' JSON');

        if (isset($decoded['inbounds'])) {
            throw new RuntimeException('Inbound #'.($index + 1).' JSON must be a single inbound object, not the full config.');
        }

        return $decoded;
    }

    private function clientsToForm(array $clients): array
    {
        $result = [];

        foreach ($clients as $client) {
            if (! is_array($client)) {
                continue;
            }

            $result[] = [
                'id' => (string) ($client['id'] ?? ''),
                'email' => (string) ($client['email'] ?? ''),
                'alterId' => $client['alterId'] ?? null,
                'security' => (string) ($client['security'] ?? ''),
                'raw_json' => $this->encode($client),
            ];
        }

        return $result;
    }

    private function clientsFromForm(array $clients): array
    {
        $results = [];

        foreach ($clients as $client) {
            if (! is_array($client) || trim((string) ($client['id'] ?? '')) === '') {
                continue;
            }

            $raw_json = trim((string) ($client['raw_json'] ?? ''));
            $client_data = $raw_json === '' ? [] : $this->decodeClientJson($raw_json);

            $client_data['id'] = trim((string) ($client['id'] ?? ''));

            $email = trim((string) ($client['email'] ?? ''));
            if ($email === '') {
                unset($client_data['email']);
            } else {
                $client_data['email'] = $email;
            }

            if (($client['alterId'] ?? null) === null || $client['alterId'] === '') {
                unset($client_data['alterId']);
            } else {
                $client_data['alterId'] = (int) $client['alterId'];
            }

            $security = trim((string) ($client['security'] ?? ''));
            if ($security === '') {
                unset($client_data['security']);
            } else {
                $client_data['security'] = $security;
            }

            $results[] = $client_data;
        }

        return $results;
    }

    private function decodeClientJson(string $json): array
    {
        $client = $this->decodeJsonObject($json, 'VMess client JSON');

        if (isset($client['clients']) || isset($client['inbounds'])) {
            throw new RuntimeException('VMess client JSON must be a single client object.');
        }

        return $client;
    }

    private function clientsAreSame(array $current_users, array $users): bool
    {
        return json_encode(array_values($current_users)) === json_encode(array_values($users));
    }

    private function firstVmessInboundIndex(array $inbounds): ?int
    {
        foreach ($inbounds as $index => $inbound) {
            if (! is_array($inbound) || ($inbound['protocol'] ?? null) !== 'vmess') {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function hasVmessInboundForPort(array $inbounds, int $port): bool
    {
        foreach ($inbounds as $inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            if ($this->isCurrentVmessInbound($inbound, $port)) {
                return true;
            }
        }

        return false;
    }

    private function defaultVmessInbound(): array
    {
        return [
            'tag' => 'main-inbound',
            'protocol' => 'vmess',
            'settings' => [
                'clients' => [],
            ],
        ];
    }

    private function decodeJsonObject(string $json, string $message): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException($message.': '.$e->getMessage(), previous: $e);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException($message.': root must be an object.');
        }

        return $decoded;
    }
}
