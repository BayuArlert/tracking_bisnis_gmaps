<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_dashboard()
    {
        $response = $this->get('/');

        $response->assertStatus(302); // Redirect to dashboard
        $response->assertRedirect('/dashboard');
    }

    public function test_api_health_check()
    {
        $response = $this->get('/api/businesses/new');

        $response->assertStatus(200);
    }
}
