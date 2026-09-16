<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * /webhook/line is public and CSRF-exempt, so the X-Line-Signature HMAC is the only
 * thing separating LINE from anyone who can reach the host.
 */
beforeEach(function () {
    config(['services.line.secret' => 'test-channel-secret']);
});

function lineSign(string $body): string
{
    return base64_encode(hash_hmac('sha256', $body, 'test-channel-secret', true));
}

test('it rejects a webhook with no signature header', function () {
    $this->postJson('/webhook/line', ['events' => []])
        ->assertStatus(403);
});

test('it rejects a webhook with a forged signature', function () {
    $body = json_encode(['events' => []]);

    $this->call('POST', '/webhook/line', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LINE_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, 'wrong-secret', true)),
    ], $body)->assertStatus(403);
});

test('it accepts a webhook signed with the channel secret', function () {
    $body = json_encode(['events' => []]);

    $this->call('POST', '/webhook/line', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LINE_SIGNATURE' => lineSign($body),
    ], $body)->assertOk();
});

test('it does not 500 on a malformed but correctly signed event', function () {
    // Previously {"events":[{}]} raised "Undefined array key" -> HTTP 500.
    $body = json_encode(['events' => [new stdClass()]]);

    $this->call('POST', '/webhook/line', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LINE_SIGNATURE' => lineSign($body),
    ], $body)->assertOk();
});

test('it does not 500 when events is not an array', function () {
    $body = json_encode(['events' => 'not-an-array']);

    $this->call('POST', '/webhook/line', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LINE_SIGNATURE' => lineSign($body),
    ], $body)->assertOk();
});
