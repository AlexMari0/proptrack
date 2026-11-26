<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never reach the network: any HTTP call without a matching
        // Http::fake() stub throws. Fonnte (WhatsApp) is stubbed for every test
        // because notifications send through it; tests may add their own stubs.
        Http::preventStrayRequests();
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);
    }
}
