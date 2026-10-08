import {test} from 'node:test';
import assert from 'node:assert/strict';
import {fetchJson} from '../../resources/js/Utils/fetchJson.js';

test('json queries retain ajax headers and same origin credentials', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (url, options) => {
    assert.equal(url, '/payment/query');
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.headers.Accept, 'application/json');
    assert.equal(options.headers['X-Requested-With'], 'XMLHttpRequest');
    return new Response(JSON.stringify({trade_status: 'TRADE_SUCCESS'}));
  };
  try {
    assert.deepEqual(await fetchJson('/payment/query'), {trade_status: 'TRADE_SUCCESS'});
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('http errors are rejected instead of being treated as payment snapshots', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response('{}', {status: 429});
  try {
    await assert.rejects(fetchJson('/payment/query'), /429/);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('an expired session returning html is rejected', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response('<html>Login</html>');
  try {
    await assert.rejects(fetchJson('/payment/query'), SyntaxError);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('slow requests time out and can be retried', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (_url, {signal}) => new Promise((resolve, reject) => {
    signal.addEventListener('abort', () => reject(signal.reason), {once: true});
  });
  try {
    await assert.rejects(fetchJson('/payment/query', {timeout: 5}), {name: 'TimeoutError'});
    globalThis.fetch = async () => new Response('{"trade_status":"WAIT_BUYER_PAY"}');
    assert.equal((await fetchJson('/payment/query')).trade_status, 'WAIT_BUYER_PAY');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('leaving the payment page cancels an in flight request', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (_url, {signal}) => new Promise((resolve, reject) => {
    signal.addEventListener('abort', () => reject(signal.reason), {once: true});
  });
  const controller = new AbortController();
  try {
    const request = fetchJson('/payment/query', {signal: controller.signal});
    controller.abort();
    await assert.rejects(request, {name: 'AbortError'});
  } finally {
    globalThis.fetch = originalFetch;
  }
});
