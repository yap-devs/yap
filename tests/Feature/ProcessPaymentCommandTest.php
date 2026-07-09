<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Supports\Collection;

test('process payment command fulfills successful alipay payments through shared status refresh', function () {
    Bus::fake();

    $user = User::factory()->create();
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123456',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')
        ->once()
        ->with(['out_trade_no' => $payment->remote_id])
        ->andReturn(new Collection([
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $payment->remote_id,
        ]));
    app()->instance('pay.alipay', $alipay);

    $this->artisan('app:process-payment-command')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PAID)
        ->and((float) $user->refresh()->balance)->toBe(5.0)
        ->and(Cache::get('alipay:trade-status:'.$payment->id))->toMatchArray([
            'trade_status' => 'TRADE_SUCCESS',
        ]);

    Bus::assertDispatched(GenerateClashProfileLink::class);
});
