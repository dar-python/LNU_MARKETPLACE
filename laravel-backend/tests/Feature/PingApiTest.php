<?php

namespace Tests\Feature;

use Tests\TestCase;

class PingApiTest extends TestCase
{
    public function test_ping_returns_ok_payload(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
            ]);
    }
}
