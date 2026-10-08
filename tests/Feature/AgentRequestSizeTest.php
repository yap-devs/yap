<?php

use App\Http\Middleware\LimitAgentRequestSize;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('agent body bounds run before global json input normalization', function () {
    expect(app(Kernel::class)->getGlobalMiddleware()[0])->toBe(LimitAgentRequestSize::class);
});

test('agent stream checking stops after the byte limit even without a reliable length header', function (bool $forged_length) {
    $limit = config('node_agent.max_request_bytes');
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, str_repeat('x', $limit * 2));
    rewind($stream);
    $server = $forged_length ? ['CONTENT_LENGTH' => '1'] : [];
    $request = Request::create('/api/agent/v1/traffic', 'POST', [], [], [], $server, $stream);
    try {
        (new LimitAgentRequestSize)->handle($request, function (): never {
            throw new LogicException('Oversized input reached JSON parsing.');
        });
        $this->fail('Oversized body was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(413)->and(ftell($stream))->toBe($limit + 1);
    } finally {
        fclose($stream);
    }
})->with([false, true]);

test('ordinary routes are not constrained by the agent byte limit', function () {
    $request = Request::create('/profile', 'POST', [], [], [], [], str_repeat('x', config('node_agent.max_request_bytes') + 1));
    $response = (new LimitAgentRequestSize)->handle($request, fn () => response('ok'));
    expect($response->getContent())->toBe('ok');
});
