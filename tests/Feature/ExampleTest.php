<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A guest hitting the dashboard is redirected to log in — the root
     * route requires auth, so 200 (this test's original assertion) is no
     * longer the correct expectation.
     */
    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
