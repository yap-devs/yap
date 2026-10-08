<?php

use App\Services\DatabaseTimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class);

function timezonePdo(string $offset): PDO
{
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('exec')->once()->with("SET SESSION time_zone = '{$offset}'")->andReturn(0);

    return $pdo;
}

function timezoneStatement(PDO $pdo, int $times): void
{
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('execute')->times($times)->andReturn(true);
    $pdo->shouldReceive('prepare')->with('SELECT 1')->times($times)->andReturn($statement);
}

test('mysql family sessions follow application timezone without named database zones', function (string $driver, string $zone, string $offset) {
    config(['app.timezone' => $zone]);
    $pdo = timezonePdo($offset);
    $connection = new MySqlConnection($pdo, 'test', '', ['driver' => $driver]);
    Event::dispatch(new ConnectionEstablished($connection));
    expect($connection->getPdo())->toBe($pdo)
        ->and(config('database.connections.'.$driver))->not->toHaveKey('timezone');
})->with([
    ['mysql', 'Asia/Tokyo', '+09:00'],
    ['mariadb', 'UTC', '+00:00'],
    ['mysql', 'Asia/Kolkata', '+05:30'],
]);

test('lazy read and write connections initialize independently on first use', function () {
    config(['app.timezone' => 'Asia/Tokyo']);
    $writer = timezonePdo('+09:00');
    $reader = timezonePdo('+09:00');
    $opened = [];
    $connection = new MySqlConnection(function () use ($writer, &$opened): PDO {
        $opened[] = 'write';

        return $writer;
    }, 'test', '', ['driver' => 'mysql']);
    $connection->setReadPdo(function () use ($reader, &$opened): PDO {
        $opened[] = 'read';

        return $reader;
    });
    app(DatabaseTimezoneService::class)->configure($connection);
    expect($opened)->toBe([]);
    expect($connection->getReadPdo())->toBe($reader)->and($opened)->toBe(['read']);
    expect($connection->getPdo())->toBe($writer)->and($opened)->toBe(['read', 'write']);
    expect($connection->getPdo())->toBe($writer);
});

test('persistent sessions refresh daylight saving offsets without repeated sql', function () {
    config(['app.timezone' => 'America/New_York']);
    CarbonImmutable::setTestNow('2026-01-01 12:00:00 UTC');
    try {
        $pdo = timezonePdo('-05:00');
        $pdo->shouldReceive('exec')->once()->with("SET SESSION time_zone = '-04:00'")->andReturn(0);
        timezoneStatement($pdo, 3);
        $connection = new MySqlConnection($pdo, 'test', '', ['driver' => 'mysql']);
        app(DatabaseTimezoneService::class)->configure($connection);
        $connection->statement('SELECT 1');
        CarbonImmutable::setTestNow('2026-07-01 12:00:00 UTC');
        $connection->statement('SELECT 1');
        $connection->statement('SELECT 1');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('reconfiguration wraps new handles once and preserves active transactions', function () {
    config(['app.timezone' => 'UTC']);
    $pdo = timezonePdo('+00:00');
    $pdo->shouldReceive('beginTransaction')->once()->andReturn(true);
    $connection = new MySqlConnection($pdo, 'test', '', ['driver' => 'mysql']);
    $service = app(DatabaseTimezoneService::class);
    $service->configure($connection);
    $connection->beginTransaction();
    $service->configure($connection);
    expect($connection->transactionLevel())->toBe(1);
    $connection->setPdo(fn (): PDO => timezonePdo('+00:00'));
    $service->configure($connection);
    $connection->getPdo();
    $property = new ReflectionProperty($connection, 'beforeExecutingCallbacks');
    expect($property->getValue($connection))->toHaveCount(1);
});

test('lost connections during offset refresh allow normal query reconnection', function () {
    config(['app.timezone' => 'UTC']);
    $pdo = timezonePdo('+00:00');
    $connection = new MySqlConnection($pdo, 'test', '', ['driver' => 'mysql']);
    app(DatabaseTimezoneService::class)->configure($connection);
    config(['app.timezone' => 'Asia/Tokyo']);
    $pdo->shouldReceive('exec')->once()->with("SET SESSION time_zone = '+09:00'")
        ->andThrow(new PDOException('MySQL server has gone away'));
    $pdo->shouldReceive('prepare')->once()->with('SELECT 1')
        ->andThrow(new PDOException('MySQL server has gone away'));
    $reconnected = timezonePdo('+09:00');
    timezoneStatement($reconnected, 1);
    $connection->setReconnector(function (MySqlConnection $connection) use ($reconnected): void {
        $connection->setPdo($reconnected);
        Event::dispatch(new ConnectionEstablished($connection));
    });
    expect($connection->statement('SELECT 1'))->toBeTrue();
});

test('timezone initialization errors cannot silently bypass session configuration', function () {
    config(['app.timezone' => 'UTC']);
    $pdo = timezonePdo('+00:00');
    $connection = new MySqlConnection($pdo, 'test', '', ['driver' => 'mysql']);
    app(DatabaseTimezoneService::class)->configure($connection);
    config(['app.timezone' => 'Asia/Tokyo']);
    $pdo->shouldReceive('exec')->once()->with("SET SESSION time_zone = '+09:00'")
        ->andThrow(new PDOException('permission denied'));
    expect(fn () => $connection->statement('SELECT 1'))->toThrow(PDOException::class, 'permission denied');
});

test('sqlite connections do not receive mysql session statements', function () {
    $pdo = new PDO('sqlite::memory:');
    $connection = new SQLiteConnection($pdo);
    app(DatabaseTimezoneService::class)->configure($connection);
    expect($connection->select('SELECT 1 AS value')[0]->value)->toBe(1);
});

test('business schedules follow the configured application timezone', function () {
    config(['app.timezone' => 'UTC']);
    $schedule = new Schedule('UTC');
    app()->instance(Schedule::class, $schedule);
    Illuminate\Support\Facades\Schedule::clearResolvedInstance(Schedule::class);
    require base_path('routes/console.php');
    expect($schedule->events())->not->toBeEmpty();
    foreach ($schedule->events() as $event) {
        expect($event->timezone)->toBe('UTC');
    }
});

test('database manager reconnect replaces both lazy handles on the original connection', function () {
    config(['app.timezone' => 'Asia/Tokyo']);
    $handles = [];
    $connections = [];
    for ($index = 0; $index < 2; $index++) {
        $writer = timezonePdo('+09:00');
        $reader = timezonePdo('+09:00');
        $handles[] = [$writer, $reader];
        $connection = new MySqlConnection(fn (): PDO => $writer, 'test', '', ['driver' => 'mysql', 'name' => 'mysql']);
        $connection->setReadPdo(fn (): PDO => $reader);
        $connections[] = $connection;
    }
    $factory = Mockery::mock(ConnectionFactory::class);
    $factory->shouldReceive('make')->with(Mockery::type('array'), 'mysql')->twice()->andReturn(...$connections);
    $manager = new DatabaseManager(app(), $factory);
    $original = $manager->connection('mysql');
    expect($original->getPdo())->toBe($handles[0][0])
        ->and($original->getReadPdo())->toBe($handles[0][1]);
    expect($manager->reconnect('mysql'))->toBe($original)
        ->and($original->getRawPdo())->toBeInstanceOf(Closure::class)
        ->and($original->getRawReadPdo())->toBeInstanceOf(Closure::class);
    expect($original->getPdo())->toBe($handles[1][0])
        ->and($original->getReadPdo())->toBe($handles[1][1]);
    $property = new ReflectionProperty($original, 'beforeExecutingCallbacks');
    expect($property->getValue($original))->toHaveCount(1);
});
