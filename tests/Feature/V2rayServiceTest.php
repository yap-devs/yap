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
    ], JSON_THROW_ON_ERROR);
}

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

    $decoded_config = json_decode($uploaded_config, true, 512, JSON_THROW_ON_ERROR);
    $all_commands = implode("\n", $commands);

    expect($decoded_config['inbounds'][0]['settings']['clients'][0]['email'])->toBe($payload)
        ->and($uploaded_destination)->toMatch('#^/tmp/yap-v2ray-[a-f0-9]{32}\.json$#')
        ->and($all_commands)->not->toContain($payload)
        ->and($all_commands)->toContain('flock -w 30')
        ->and($all_commands)->toContain('/usr/local/bin/v2ray test -config')
        ->and($all_commands)->toContain('systemctl is-active --quiet v2ray')
        ->and($all_commands)->toContain('mv -f');
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
    'config validation' => [20, 'V2ray rejected the uploaded configuration.'],
    'deployment' => [23, 'V2ray configuration deployment failed.'],
    'restart with successful rollback' => [40, 'the previous configuration was restored'],
    'rollback restart' => [31, 'V2ray configuration rollback failed.'],
]);
