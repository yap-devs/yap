<?php

test('sentry reporting is disabled during automated tests', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('sentry.dsn'))->toBeEmpty();
});
