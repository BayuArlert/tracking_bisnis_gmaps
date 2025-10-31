<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_login_when_not_authenticated()
    {
        $response = $this->get('/');

        $response->assertStatus(302); // Redirect to login
        $response->assertRedirect('/login');
    }

    public function test_api_health_check()
    {
        // Test with required 'area' parameter
        $response = $this->get('/api/businesses/new?area=Badung');

        $response->assertStatus(200);
    }
}
