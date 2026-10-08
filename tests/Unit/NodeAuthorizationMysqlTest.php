<?php

use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\TwentyFourHourTrafficRankingTable;
use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\Payment;
use App\Models\TrafficRecord;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (getenv('YAP_RUN_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Set YAP_RUN_MYSQL_TESTS=1 to use a disposable local MySQL database.');
    }
    $socket = getenv('YAP_MYSQL_TEST_SOCKET') ?: '/run/mysqld/mysqld.sock';
    $username = getenv('YAP_MYSQL_TEST_USERNAME') ?: 'root';
    $password = getenv('YAP_MYSQL_TEST_PASSWORD') ?: '';
    $this->admin_pdo = new PDO('mysql:unix_socket='.$socket, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $this->mysql_database = 'yap_agent_test_'.bin2hex(random_bytes(12));
    $this->admin_pdo->exec('CREATE DATABASE `'.$this->mysql_database.'` CHARACTER SET utf8mb4');
    $this->mysql_owned = true;
    $this->mysql_directory = sys_get_temp_dir().'/yap-mysql-'.bin2hex(random_bytes(12));
    mkdir($this->mysql_directory, 0700);
    $this->mysql_processes = [];
    config([
        'database.default' => 'mysql', 'database.connections.mysql.url' => null,
        'database.connections.mysql.host' => 'localhost', 'database.connections.mysql.database' => $this->mysql_database,
        'database.connections.mysql.unix_socket' => $socket, 'database.connections.mysql.username' => $username,
        'database.connections.mysql.password' => $password, 'node_agent.enabled' => false,
        'cache.default' => 'array', 'queue.default' => 'database', 'queue.connections.database.connection' => null,
        'queue.connections.database.table' => 'jobs', 'queue.connections.database.queue' => 'default', 'sub2api.enabled' => false,
    ]);
    DB::purge('mysql');
    Notification::fake();
    Http::preventStrayRequests();
    expect(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]))->toBe(0);
    $node = Node::factory()->create();
    $route = NodeRoute::factory()->for($node)->create();
    $user = User::factory()->create(['balance' => '10.00', 'github_created_at' => null]);
    $package = Package::create(['name' => 'Concurrency fixture', 'price' => '1.00', 'traffic_limit' => 1073741824, 'status' => Package::STATUS_ACTIVE]);
    $payment = Payment::create(['user_id' => $user->id, 'amount' => '10.00', 'status' => Payment::STATUS_CREATED, 'gateway' => 'stripe', 'remote_id' => (string) Str::uuid()]);
    $this->owner = [
        'database' => $this->mysql_database, 'token' => bin2hex(random_bytes(24)),
        'node_id' => $node->id, 'route_id' => $route->id, 'user_id' => $user->id,
        'package_id' => $package->id, 'payment_id' => $payment->id, 'batch_uuid' => (string) Str::uuid(),
    ];
    file_put_contents($this->mysql_directory.'/owner.json', json_encode($this->owner, JSON_THROW_ON_ERROR));
    chmod($this->mysql_directory.'/owner.json', 0600);
    config(['node_agent.enabled' => true]);
});

afterEach(function () {
    foreach ($this->mysql_processes ?? [] as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    if ($this->mysql_owned ?? false) {
        DB::purge('mysql');
        // Undo only the unique database created by this test, never an existing database.
        $this->admin_pdo->exec('DROP DATABASE `'.$this->mysql_database.'`');
    }
    if (isset($this->mysql_directory)) {
        (new Filesystem)->deleteDirectory($this->mysql_directory);
    }
});

function authorizationMysqlWorker(object $test, string $mode): Process
{
    $process = new Process([PHP_BINARY, dirname(__DIR__).'/Fixtures/NodeAuthorizationWorker.php', $mode], null, [
        'YAP_MYSQL_TEST_DIR' => $test->mysql_directory, 'YAP_MYSQL_TEST_TOKEN' => $test->owner['token'],
    ]);
    $process->setTimeout(20);
    $process->start();
    $test->mysql_processes[] = $process;

    return $process;
}

function waitForAuthorizationBarrier(string $path): void
{
    $deadline = microtime(true) + 8;
    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }
}

test('mysql financial changes and agent traffic do not invert node and user locks', function (string $mode) {
    if ($mode === 'payment') {
        User::whereKey($this->owner['user_id'])->update(['balance' => '0.00']);
    }
    $financial = authorizationMysqlWorker($this, $mode);
    waitForAuthorizationBarrier($this->mysql_directory.'/financial-user-locked');
    $traffic = authorizationMysqlWorker($this, 'traffic');
    waitForAuthorizationBarrier($this->mysql_directory.'/traffic-node-locked');
    touch($this->mysql_directory.'/release-financial');
    $financial->wait();
    $traffic->wait();
    expect($financial->getExitCode())->toBe(0, $financial->getErrorOutput())
        ->and($traffic->getExitCode())->toBe(0, $traffic->getErrorOutput());
    $user = User::findOrFail($this->owner['user_id']);
    expect($user->balance)->toBe($mode === 'payment' ? '10.00' : '9.00')
        ->and((int) $user->traffic_uplink)->toBe(100)
        ->and((int) $user->traffic_downlink)->toBe(200)
        ->and(TrafficRecord::count())->toBe(1)
        ->and(Node::findOrFail($this->owner['node_id'])->desired_revision)->toBeGreaterThan(1)
        ->and(DB::table('jobs')->count())->toBe(0);
    if ($mode === 'payment') {
        expect(Payment::findOrFail($this->owner['payment_id'])->status)->toBe(Payment::STATUS_PAID);
    } else {
        expect($user->packages()->count())->toBe(1);
    }
})->with(['payment', 'purchase']);

test('mysql committed authorization notification survives process exit before refresh', function () {
    User::whereKey($this->owner['user_id'])->update(['balance' => '0.00']);
    $revision = Node::findOrFail($this->owner['node_id'])->desired_revision;
    $process = authorizationMysqlWorker($this, 'crash');
    $process->wait();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(User::findOrFail($this->owner['user_id'])->balance)->toBe('10.00')
        ->and(Node::findOrFail($this->owner['node_id'])->desired_revision)->toBe($revision)
        ->and(DB::table('jobs')->count())->toBe(1);
    expect(Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 3]))->toBe(0);
    expect(Node::findOrFail($this->owner['node_id'])->desired_revision)->toBeGreaterThan($revision)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('mysql strict mode accepts grouped traffic widget sorting', function (string $widget, string $column) {
    expect(DB::selectOne('SELECT @@session.sql_mode AS mode')->mode)->toContain('ONLY_FULL_GROUP_BY');
    $admin = User::findOrFail(1);
    $this->actingAs($admin);
    $report_user = User::factory()->create(['id' => 6]);
    DB::table('user_stats')->insert([
        'user_id' => $report_user->id, 'traffic_uplink' => 100,
        'traffic_downlink' => 200, 'created_at' => now()->subMinutes(2), 'updated_at' => now(),
    ]);
    $component = Livewire::test($widget)->assertOk();
    expect($component->instance()->getFilteredSortedTableQuery()->limit(9)->get())->toHaveCount(1);
    foreach (['asc', 'desc'] as $direction) {
        $component->sortTable($column, $direction)->assertOk();
        expect($component->instance()->getFilteredSortedTableQuery()->limit(9)->get())->toHaveCount(1);
    }
})->with([
    'daily traffic' => [DailyTrafficRankingTable::class, 'daily_traffic_bytes'],
    'daily day' => [DailyTrafficRankingTable::class, 'day'],
    'rolling traffic' => [TwentyFourHourTrafficRankingTable::class, 'total_traffic_bytes'],
]);
