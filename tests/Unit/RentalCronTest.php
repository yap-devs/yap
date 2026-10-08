<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->cron_root = sys_get_temp_dir().'/yap cron '.bin2hex(random_bytes(6));
    $this->filesystem = new Filesystem;
    $this->filesystem->copyDirectory(dirname(__DIR__, 2).'/docs/deployment/rental-cron', $this->cron_root);
    mkdir($this->cron_root.'/app');
    mkdir($this->cron_root.'/bin');
    touch($this->cron_root.'/app/artisan');
    file_put_contents($this->cron_root.'/bin/php', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$CALL_LOG"
if [ "${HOLD_COMMAND:-}" = "$2" ]; then
    while [ ! -f "$RELEASE_FILE" ]; do
        /bin/sleep 0.02
    done
fi
/bin/sleep 0.1
exit "${MOCK_STATUS:-0}"
SH);
    file_put_contents($this->cron_root.'/bin/date', "#!/bin/sh\nprintf '%s\\n' \"\${MOCK_EPOCH:-1800000000}\"\n");
    file_put_contents($this->cron_root.'/bin/sleep', "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> \"\$SLEEP_LOG\"\n");
    foreach (['php', 'date', 'sleep'] as $binary) {
        chmod($this->cron_root.'/bin/'.$binary, 0755);
    }
    $quote = fn (string $value): string => "'".str_replace("'", "'\\''", $value)."'";
    file_put_contents($this->cron_root.'/config.sh', implode("\n", [
        'umask 077',
        'PHP_BIN='.$quote($this->cron_root.'/bin/php'),
        'APP_DIR='.$quote($this->cron_root.'/app'),
        'CRON_STATE='.$quote($this->cron_root.'/state'),
        'YAP_ENABLED=1',
        'export PHP_BIN APP_DIR CRON_STATE YAP_ENABLED',
        '',
    ]));
    $this->cron_env = [
        'PATH' => $this->cron_root.'/bin:'.getenv('PATH'),
        'CALL_LOG' => $this->cron_root.'/calls',
        'SLEEP_LOG' => $this->cron_root.'/sleeps',
        'RELEASE_FILE' => $this->cron_root.'/release',
    ];
});

test('rental scheduler accepts a new minute while the previous tick is still running', function () {
    $first = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'HOLD_COMMAND' => 'schedule:run']);
    $first->start();
    try {
        $deadline = microtime(true) + 5;
        do {
            $calls = is_file($this->cron_root.'/calls') ? file($this->cron_root.'/calls', FILE_IGNORE_NEW_LINES) : [];
            if ($calls !== []) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($calls)->toHaveCount(1)->and($first->isRunning())->toBeTrue();

        $next = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'MOCK_EPOCH' => '1800000060']);
        expect($next->run())->toBe(0)->and($first->isRunning())->toBeTrue();
        $calls = file($this->cron_root.'/calls', FILE_IGNORE_NEW_LINES);
        expect(count(array_filter($calls, fn (string $call): bool => str_contains($call, 'schedule:run'))))->toBe(2)
            ->and(count(array_filter($calls, fn (string $call): bool => str_contains($call, 'queue:work'))))->toBe(1);
        $repeat = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'MOCK_EPOCH' => '1800000060']);
        expect($repeat->run())->toBe(0)->and(file($this->cron_root.'/calls'))->toHaveCount(3);
    } finally {
        touch($this->cron_root.'/release');
        $first->wait();
    }
});

test('rental queue retains its execution lock across minutes', function () {
    $first = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'HOLD_COMMAND' => 'queue:work']);
    $first->start();
    try {
        $deadline = microtime(true) + 5;
        do {
            $calls = is_file($this->cron_root.'/calls') ? file($this->cron_root.'/calls', FILE_IGNORE_NEW_LINES) : [];
            if (count($calls) === 2) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($calls)->toHaveCount(2)->and($first->isRunning())->toBeTrue();

        $next = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'MOCK_EPOCH' => '1800000060']);
        expect($next->run())->toBe(0)->and($first->isRunning())->toBeTrue();
        $calls = file($this->cron_root.'/calls', FILE_IGNORE_NEW_LINES);
        expect(count(array_filter($calls, fn (string $call): bool => str_contains($call, 'queue:work'))))->toBe(1)
            ->and(count(array_filter($calls, fn (string $call): bool => str_contains($call, 'schedule:run'))))->toBe(2);
    } finally {
        touch($this->cron_root.'/release');
        $first->wait();
    }
});

afterEach(function () {
    $this->filesystem->deleteDirectory($this->cron_root);
});

test('rental dispatcher deduplicates concurrent and repeated minute invocations', function () {
    $first = new Process(['sh', $this->cron_root.'/cron.sh'], null, $this->cron_env);
    $second = new Process(['sh', $this->cron_root.'/cron.sh'], null, $this->cron_env);
    $first->start();
    $second->start();
    expect($first->wait())->toBe(0)->and($second->wait())->toBe(0);
    $repeat = new Process(['sh', $this->cron_root.'/cron.sh'], null, $this->cron_env);
    expect($repeat->run())->toBe(0);
    $calls = file($this->cron_root.'/calls', FILE_IGNORE_NEW_LINES);
    expect($calls)->toHaveCount(2)
        ->and(implode("\n", $calls))->toContain('artisan schedule:run --no-interaction', 'artisan queue:work database')
        ->and(glob($this->cron_root.'/state/*.last'))->toHaveCount(2);
});

test('rental delayed launchers invoke the dispatcher at their configured offsets', function (int $slot) {
    $process = new Process(['sh', $this->cron_root.'/cron-'.$slot.'.sh'], null, $this->cron_env);
    expect($process->run())->toBe(0);
    expect(trim(file_get_contents($this->cron_root.'/sleeps')))->toBe((string) ($slot * 60));
    expect(file($this->cron_root.'/calls'))->toHaveCount(2);
})->with([0, 1, 2, 3, 4]);

test('rental cron reports failures without repeating an attempt in the same bucket', function () {
    $environment = [...$this->cron_env, 'MOCK_STATUS' => '7'];
    $process = new Process(['sh', $this->cron_root.'/cron.sh'], null, $environment);
    expect($process->run())->toBe(1)
        ->and($process->getErrorOutput())->toContain('scheduler exited with status 7', 'queue exited with status 7');
    $repeat = new Process(['sh', $this->cron_root.'/cron.sh'], null, $environment);
    expect($repeat->run())->toBe(0)->and(file($this->cron_root.'/calls'))->toHaveCount(2);
    $next = new Process(['sh', $this->cron_root.'/cron.sh'], null, [...$this->cron_env, 'MOCK_EPOCH' => '1800000060']);
    expect($next->run())->toBe(0)->and(file($this->cron_root.'/calls'))->toHaveCount(4);
});

test('disabled rental cron and missing private configuration do not run artisan', function () {
    file_put_contents($this->cron_root.'/config.sh', "\nYAP_ENABLED=0\n", FILE_APPEND);
    $process = new Process(['sh', $this->cron_root.'/cron.sh'], null, $this->cron_env);
    expect($process->run())->toBe(0)->and(file_exists($this->cron_root.'/calls'))->toBeFalse();
    unlink($this->cron_root.'/config.sh');
    $missing = new Process(['sh', $this->cron_root.'/cron.sh'], null, $this->cron_env);
    expect($missing->run())->toBe(1)->and($missing->getErrorOutput())->toContain('Create private config.sh');
});
