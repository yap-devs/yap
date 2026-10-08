<?php

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

test('sentry reporting is disabled during automated tests', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('sentry.dsn'))->toBeEmpty();
});

test('agent performance transactions are excluded while errors remain reportable', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->twice()->andReturnUsing(fn (Event $event): Result => new Result(ResultStatus::success(), $event));
    $client = ClientBuilder::create([
        'dsn' => 'https://public@example.invalid/1',
        'default_integrations' => false,
        'ignore_transactions' => config('sentry.ignore_transactions'),
        'sample_rate' => 1.0,
    ])->setTransport($transport)->getClient();

    foreach (['/api/agent/v1/config', '/api/agent/v1/traffic'] as $name) {
        $transaction = Event::createTransaction();
        $transaction->setTransaction($name);
        expect($client->captureEvent($transaction))->toBeNull();
    }

    $error = Event::createEvent();
    $error->setTransaction('/api/agent/v1/traffic');
    $error->setMessage('Agent database failure');
    expect($client->captureEvent($error))->not->toBeNull();

    $transaction = Event::createTransaction();
    $transaction->setTransaction('/dashboard');
    expect($client->captureEvent($transaction))->not->toBeNull();
});
