<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Until this guard existed the suite talked to the real internet: every run
     * pushed to the live LINE group with the production channel token, and the
     * log shows 523 such calls from the `testing` environment alone - the bulk
     * of what exhausted the channel's monthly message quota on 3 Sep 2026.
     *
     * Http::fake() answers every outbound request locally, and
     * preventStrayRequests() turns any request that somehow escapes the fake
     * into a failed test rather than a real API call. A test that needs a
     * specific response still calls Http::fake([...]) itself; that overrides
     * this default.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake();
    }
}
