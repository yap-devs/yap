<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\Payment;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserStat;
use App\Services\PaymentFulfillmentService;
use App\Services\TrafficAggregationService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

$directory = getenv('YAP_E2E_DIR');
$control_token = getenv('YAP_E2E_CONTROL_TOKEN');
$temporary_root = realpath(sys_get_temp_dir());
if (! in_array(PHP_SAPI, ['cli', 'cli-server'], true)
    || ! is_string($directory) || ! str_starts_with($directory, '/')
    || realpath($directory) !== $directory || ! str_starts_with($directory, $temporary_root.'/')
    || ! is_string($control_token) || strlen($control_token) < 32) {
    http_response_code(403);
    exit("An isolated temporary directory and control token are required.\n");
}
$database = $directory.'/panel.sqlite';
$marker = $directory.'/panel-owner';
$initialize = PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'init';
if ($initialize) {
    $port = filter_var(getenv('YAP_E2E_ROUTE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65534]]);
    if (! $port || file_exists($marker) || file_exists($database)) {
        fwrite(STDERR, "Initialization requires a fresh directory and valid YAP_E2E_ROUTE_PORT.\n");
        exit(1);
    }
    $handle = fopen($database, 'x');
    if ($handle === false) {
        fwrite(STDERR, "Could not exclusively create fixture database.\n");
        exit(1);
    }
    fclose($handle);
    chmod($database, 0600);
    file_put_contents($marker, hash('sha256', $control_token), LOCK_EX);
    chmod($marker, 0600);
} elseif (! is_file($database) || is_link($database) || ! is_file($marker)
    || ! hash_equals(file_get_contents($marker), hash('sha256', $control_token))) {
    http_response_code(403);
    exit("Fixture has not been initialized for this control token.\n");
}

foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('e', 32)),
    'APP_CONFIG_CACHE' => $directory.'/config-cache.php',
    'APP_ROUTES_CACHE' => $directory.'/routes-cache.php',
    'APP_SERVICES_CACHE' => $directory.'/services-cache.php',
    'APP_PACKAGES_CACHE' => $directory.'/packages-cache.php',
    'APP_EVENTS_CACHE' => $directory.'/events-cache.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false', 'SENTRY_DSN' => '', 'SENTRY_LARAVEL_DSN' => '',
    'LOG_CHANNEL' => 'stderr', 'NODE_AGENT_ENABLED' => 'true', 'NODE_SUBSCRIPTIONS_ENABLED' => 'true',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($directory)->loadEnvironmentFrom('fixture-environment-does-not-exist');
$app->make(ConsoleKernel::class)->bootstrap();
config([
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
    'database.connections.sqlite.url' => null,
    'cache.default' => 'file', 'cache.stores.file.path' => $directory.'/cache',
    'cache.stores.file.lock_path' => $directory.'/cache',
    'node_agent.snapshot_store' => 'file', 'subscription.content_store' => 'file',
    'subscription.lock_store' => 'database', 'yap.unit_price' => '0.02',
    'node_agent.enabled' => true, 'node_agent.subscriptions_enabled' => true,
    'node_agent.poll_interval_seconds' => 2, 'node_agent.traffic_interval_seconds' => 10,
]);
Notification::fake();
Bus::fake();
Http::preventStrayRequests();

if ($initialize) {
    if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }
    $node = Node::create([
        'name' => 'Fixture Node', 'enabled' => true, 'traffic_source' => 'agent',
        'agent_token_hash' => hash('sha256', 'yap-e2e-agent-token-0123456789abcdef0123456789abcdef'),
        'core_config' => ['inbounds' => [['tag' => 'yap-main', 'protocol' => 'vmess', 'listen' => '127.0.0.1', 'streamSettings' => ['network' => 'tcp']]], 'outbounds' => [['protocol' => 'freedom']]],
    ]);
    foreach (['1.25', '3.00'] as $offset => $rate) {
        NodeRoute::create(['node_id' => $node->id, 'name' => 'Fixture Route '.($offset + 1), 'server' => '127.0.0.1', 'port' => $port + $offset, 'listen_port' => $port + $offset, 'inbound_tag' => 'yap-main', 'rate' => $rate, 'enabled' => true, 'for_low_priority' => true]);
    }
    $user = User::factory()->create(['id' => 42, 'name' => 'Fixture User', 'email' => 'fixture@example.invalid', 'uuid' => 'c2cda8b8-6c14-4c08-aef0-560f4102a819', 'balance' => '1.00', 'github_created_at' => null]);
    User::factory()->create(['id' => 43, 'name' => 'New Fixture User', 'email' => 'new-fixture@example.invalid', 'uuid' => 'c2cda8b8-6c14-4c08-aef0-560f4102a820', 'balance' => '0.00', 'github_created_at' => null]);
    $package = Package::create(['name' => 'Fixture Package', 'price' => 1, 'traffic_limit' => 2097152]);
    UserPackage::create(['user_id' => $user->id, 'package_id' => $package->id, 'remaining_traffic' => 2097152, 'priority' => 0, 'status' => UserPackage::STATUS_ACTIVE, 'started_at' => now()->subMinute(), 'ended_at' => now()->addDay()]);
    echo json_encode(['initialized' => true, 'node_id' => $node->id, 'user_id' => $user->id], JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

$request = Request::capture();
if (str_starts_with($request->getPathInfo(), '/__test/')) {
    if (! hash_equals($control_token, (string) $request->bearerToken())) {
        response()->json(['error' => 'unauthorized'], 401)->send();
        exit;
    }
    try {
        $path = $request->getPathInfo();
        $user = User::findOrFail(42);
        if ($request->isMethod('POST')) {
            match ($path) {
                '/__test/recharge' => app(PaymentFulfillmentService::class)->fulfill(Payment::create([
                    'user_id' => in_array($request->integer('user_id', 42), [42, 43], true) ? $request->integer('user_id', 42) : abort(422), 'gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_CREATED,
                    'amount' => $request->input('amount', '1.00'),
                ])),
                '/__test/rate' => NodeRoute::findOrFail($request->integer('route_id', 1))->update(['rate' => $request->input('rate', '2.00')]),
                '/__test/expire-package' => DB::transaction(function () use ($user): void {
                    foreach ($user->packages()->get() as $package) {
                        $package->update(['status' => UserPackage::STATUS_EXPIRED, 'ended_at' => now()->subSecond()]);
                    }
                    $user->update(['balance' => 0]);
                }),
                '/__test/aggregate' => (function (): int {
                    TrafficBatch::whereNull('aggregated_at')->update(['received_at' => now()->subHour()]);

                    return app(TrafficAggregationService::class)->aggregate();
                })(),
                default => abort(404),
            };
        } else {
            abort_unless($request->isMethod('GET') && $path === '/__test/state', 404);
        }
        $user = $user->fresh();
        response()->json([
            'user' => $user->only(['id', 'uuid', 'balance', 'traffic_uplink', 'traffic_downlink', 'traffic_unpaid', 'is_valid', 'is_low_priority']),
            'new_user' => User::findOrFail(43)->only(['id', 'uuid', 'balance', 'traffic_uplink', 'traffic_downlink', 'traffic_unpaid', 'is_valid', 'is_low_priority']),
            'node' => Node::firstOrFail()->only(['id', 'desired_revision', 'applied_revision', 'traffic_source']),
            'routes' => NodeRoute::orderBy('id')->get()->toArray(),
            'package' => UserPackage::first()->only(['id', 'status', 'remaining_traffic']),
            'batch_count' => TrafficBatch::count(), 'record_count' => TrafficRecord::count(),
            'batches' => TrafficBatch::orderBy('id')->get()->toArray(),
            'records' => TrafficRecord::orderBy('id')->get()->toArray(),
            'latest_record' => TrafficRecord::latest('id')->first(),
            'stats' => UserStat::orderBy('id')->get()->toArray(),
            'payment_count' => Payment::where('status', Payment::STATUS_PAID)->count(),
            'balance_details' => $user->balanceDetails()->get()->toArray(),
        ])->send();
    } catch (Throwable $exception) {
        response()->json(['error' => $exception->getMessage()], 422)->send();
    }
    exit;
}
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
