<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** Databases the suite may touch (local copies only, never production). */
    private const ALLOWED_DATABASES = ['cablebill02nov', 'cablebill_test'];

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.mysql.database');
        $host = (string) config('database.connections.mysql.host');
        if (! in_array($db, self::ALLOWED_DATABASES, true) || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->fail("Refusing to run: tests may only use a local copy ($db @ $host).");
        }
        config(['cablebill.sms.enabled' => false]);   // no real SMS, ever
        Http::preventStrayRequests();                 // nothing may reach the network unless the test fakes it
    }
}
