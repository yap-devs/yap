<?php

use App\Services\V2rayService;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

function v2rayProcess(bool $successful, string $output = '', int $exit_code = 0, string $error_output = ''): Process
{
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('isSuccessful')->andReturn($successful);
    $process->shouldReceive('getOutput')->andReturn($output);
    $process->shouldReceive('getErrorOutput')->andReturn($error_output);
    $process->shouldReceive('getExitCode')->andReturn($exit_code);

    return $process;
}

function validV2rayConfig(): string
{
    return json_encode([
        'inbounds' => [[
            'settings' => [
                'clients' => [[
                    'id' => 'old-user-id',
                    'email' => 'old@example.com',
                ]],
            ],
        ]],
        'outbounds' => [],
        'policy' => [
            'levels' => (object) [
                '0' => [
                    'statsUserDownlink' => true,
                    'statsUserUplink' => true,
                ],
            ],
        ],
        'stats' => (object) [],
    ], JSON_THROW_ON_ERROR);
}

test('invalid server addresses are rejected before creating ssh commands', function (string $server, string $message) {
    expect(fn () => new V2rayService($server))
        ->toThrow(UnexpectedValueException::class, $message);
})->with([
    'shell payload in hostname' => ['node.example.com;id', 'Invalid V2ray server host.'],
    'invalid port' => ['node.example.com:70000', 'Invalid V2ray server port.'],
]);

test('user payload is uploaded as json and never appears in remote commands', function () {
    $payload = "attacker@example.com'; touch /tmp/injected; #";
    $commands = [];
    $uploaded_config = null;
    $uploaded_destination = null;
    $ssh = Mockery::mock(Ssh::class);

    $ssh->shouldReceive('execute')->andReturnUsing(function (string $command) use (&$commands) {
        $commands[] = $command;

        return str_starts_with($command, 'cat ')
            ? v2rayProcess(true, validV2rayConfig())
            : v2rayProcess(true);
    });
    $ssh->shouldReceive('upload')->once()->andReturnUsing(
        function (string $source, string $destination) use (&$uploaded_config, &$uploaded_destination) {
            $uploaded_config = file_get_contents($source);
            $uploaded_destination = $destination;

            return v2rayProcess(true);
        }
    );

    (new V2rayService('node-1.example.com:22', $ssh))->addOrRemoveUsers([[
        'id' => 'new-user-id',
        'email' => $payload,
    ]]);

    $decoded_config = json_decode($uploaded_config, false, 512, JSON_THROW_ON_ERROR);
    $all_commands = implode("\n", $commands);

    expect($decoded_config->inbounds[0]->settings->clients[0]->email)->toBe($payload)
        ->and($decoded_config->policy->levels)->toBeInstanceOf(stdClass::class)
        ->and($decoded_config->policy->levels->{'0'}->statsUserUplink)->toBeTrue()
        ->and($decoded_config->stats)->toBeInstanceOf(stdClass::class)
        ->and($uploaded_destination)->toMatch('#^/tmp/yap-v2ray-[a-f0-9]{32}\.json$#')
        ->and($all_commands)->not->toContain($payload)
        ->and($all_commands)->toContain('flock -w 30')
        ->and($all_commands)->toContain('/usr/local/bin/v2ray test -config')
        ->and($all_commands)->toContain('systemctl is-active --quiet v2ray')
        ->and($all_commands)->toContain('mv -f');
});

test('current users are compared without rewriting an unchanged configuration', function () {
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->once()->with('cat /usr/local/etc/v2ray/config.json')
        ->andReturn(v2rayProcess(true, validV2rayConfig()));
    $ssh->shouldNotReceive('upload');

    (new V2rayService('node-1.example.com', $ssh))->addOrRemoveUsers([[
        'id' => 'old-user-id',
        'email' => 'old@example.com',
    ]]);
});

test('invalid current configs fail closed without uploading', function (string $config) {
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->once()->with('cat /usr/local/etc/v2ray/config.json')
        ->andReturn(v2rayProcess(true, $config));
    $ssh->shouldNotReceive('upload');

    expect(fn () => (new V2rayService('node-1.example.com', $ssh))->addOrRemoveUsers([]))
        ->toThrow(UnexpectedValueException::class);
})->with([
    'empty config' => '',
    'invalid json' => '{invalid',
    'missing inbound settings' => '{"inbounds":[]}',
]);

test('a failed remote config read fails closed without uploading', function () {
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->once()->andReturn(v2rayProcess(false, '', 1, 'read failed'));
    $ssh->shouldNotReceive('upload');

    expect(fn () => (new V2rayService('node-1.example.com', $ssh))->addOrRemoveUsers([]))
        ->toThrow(RuntimeException::class, 'Unable to read current V2ray configuration');
});

test('a failed upload throws and attempts remote cleanup', function () {
    $commands = [];
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->andReturnUsing(function (string $command) use (&$commands) {
        $commands[] = $command;

        return str_starts_with($command, 'cat ')
            ? v2rayProcess(true, validV2rayConfig())
            : v2rayProcess(true);
    });
    $ssh->shouldReceive('upload')->once()->andReturn(v2rayProcess(false, '', 1, 'upload failed'));

    expect(fn () => (new V2rayService('node-1.example.com', $ssh))->addOrRemoveUsers([[
        'id' => 'new-user-id',
        'email' => 'user@example.com',
    ]]))->toThrow(RuntimeException::class, 'Unable to upload V2ray configuration');

    expect(array_filter($commands, fn (string $command) => str_starts_with($command, 'rm -f -- ')))
        ->toHaveCount(2);
});

test('deployment stage failures throw sanitized exceptions', function (int $exit_code, string $message) {
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->andReturnUsing(function (string $command) use ($exit_code) {
        if (str_starts_with($command, 'cat ')) {
            return v2rayProcess(true, validV2rayConfig());
        }

        if (str_starts_with($command, 'flock ')) {
            return v2rayProcess(false, '', $exit_code, 'sensitive validation output');
        }

        return v2rayProcess(true);
    });
    $ssh->shouldReceive('upload')->once()->andReturn(v2rayProcess(true));

    expect(fn () => (new V2rayService('node-1.example.com', $ssh))->addOrRemoveUsers([[
        'id' => 'new-user-id',
        'email' => 'user@example.com',
    ]]))->toThrow(RuntimeException::class, $message);
})->with([
    'config validation' => [20, 'V2ray rejected the uploaded configuration. (exit code 20).'],
    'deployment' => [23, 'V2ray configuration deployment failed. (exit code 23).'],
    'restart with successful rollback' => [40, 'the previous configuration was restored. (exit code 40).'],
    'rollback restart' => [31, 'V2ray configuration rollback failed. (exit code 31).'],
]);
