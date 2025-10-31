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
        // Skip this test as it requires actual API integration
        // API scraping tests should be integration tests with proper mocking
        $this->markTestSkipped('API test skipped - requires Google Places API integration');
        
        // If unskipped, test with required 'area' parameter
        $response = $this->get('/api/businesses/new?area=Badung');
        $response->assertStatus(200);
    }
}
