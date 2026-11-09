<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_timezone_is_ist(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
    }
}
