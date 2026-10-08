<?php

use App\Http\Controllers\PackageController;
use App\Models\Node;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use App\Services\PaymentFulfillmentService;
use App\Services\TrafficIngestionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

$directory = getenv('YAP_MYSQL_TEST_DIR');
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || ! is_string($directory) || realpath($directory) !== $directory
    || ! str_starts_with($directory, realpath(sys_get_temp_dir()).'/yap-mysql-')) {
    exit(1);
}
$owner = json_decode(file_get_contents($directory.'/owner.json'), true, 512, JSON_THROW_ON_ERROR);
if (! preg_match('/\Ayap_agent_test_[a-f0-9]{24}\z/', $owner['database'] ?? '')
    || ! hash_equals($owner['token'], (string) getenv('YAP_MYSQL_TEST_TOKEN'))) {
    exit(1);
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/unused-config.php', 'TELESCOPE_ENABLED' => 'false', 'SENTRY_DSN' => '', 'SENTRY_LARAVEL_DSN' => '', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($directory)->loadEnvironmentFrom('unused-environment');
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'mysql', 'database.connections.mysql.url' => null,
    'database.connections.mysql.host' => 'localhost', 'database.connections.mysql.database' => $owner['database'],
    'database.connections.mysql.unix_socket' => getenv('YAP_MYSQL_TEST_SOCKET') ?: '/run/mysqld/mysqld.sock',
    'database.connections.mysql.username' => getenv('YAP_MYSQL_TEST_USERNAME') ?: 'root',
    'database.connections.mysql.password' => getenv('YAP_MYSQL_TEST_PASSWORD') ?: '',
    'cache.default' => 'array', 'queue.default' => 'database', 'queue.connections.database.connection' => null,
    'queue.connections.database.table' => 'jobs', 'queue.connections.database.queue' => 'default', 'node_agent.enabled' => true,
    'yap.unit_price' => '0.02', 'sub2api.enabled' => false,
]);
Notification::fake();
Bus::fake();
Http::preventStrayRequests();
DB::statement('SET SESSION innodb_lock_wait_timeout = 10');

$wait = function (string $name) use ($directory): void {
    $deadline = microtime(true) + 12;
    while (! is_file($directory.'/'.$name)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout: '.$name);
        }
        usleep(10000);
    }
};
if ($mode === 'traffic') {
    DB::connection()->beforeExecuting(function (string $query) use ($directory): void {
        if (str_contains($query, 'from `users`') && str_contains($query, 'for update')) {
            touch($directory.'/traffic-node-locked');
        }
    });
    app(TrafficIngestionService::class)->ingest(Node::findOrFail($owner['node_id']), $owner['batch_uuid'], [[
        'user_id' => $owner['user_id'], 'route_id' => $owner['route_id'], 'uplink' => 100, 'downlink' => 200,
    ]]);
} elseif (in_array($mode, ['purchase', 'payment', 'crash'], true)) {
    DB::transaction(function () use ($mode, $directory, $owner, $wait): void {
        $user = User::lockForUpdate()->findOrFail($owner['user_id']);
        touch($directory.'/financial-user-locked');
        if ($mode === 'crash') {
            DB::afterCommit(fn () => exit(0));
            $user->update(['balance' => '10.00']);

            return;
        }
        $wait('release-financial');
        if ($mode === 'payment') {
            app(PaymentFulfillmentService::class)->fulfill(Payment::findOrFail($owner['payment_id']));
        } else {
            $request = Request::create('/package', 'POST');
            $request->setUserResolver(fn (): User => $user);
            app(PackageController::class)->buy($request, Package::findOrFail($owner['package_id']), app(AffiliateService::class));
        }
    });
} else {
    exit(1);
}
echo "completed\n";
