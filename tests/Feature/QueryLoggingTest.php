<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueryLoggingTest extends TestCase
{
    public function test_query_logging_can_be_enabled()
    {
        Config::set('database.logging', true);
        $this->assertTrue(Config::get('database.logging'));
    }

    public function test_query_logging_can_be_disabled()
    {
        Config::set('database.logging', false);
        $this->assertFalse(Config::get('database.logging'));
    }
}
