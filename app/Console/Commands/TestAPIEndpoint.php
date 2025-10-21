<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\BusinessController;
use Illuminate\Http\Request;

class TestAPIEndpoint extends Command
{
    protected $signature = 'test:api-endpoint {kabupaten} {kecamatan}';
    protected $description = 'Test API endpoint directly with parameters';

    public function handle()
    {
        $kabupaten = $this->argument('kabupaten');
        $kecamatan = $this->argument('kecamatan');
        
        $this->info("=== TESTING API ENDPOINT ===");
        $this->info("Kabupaten: $kabupaten");
        $this->info("Kecamatan: $kecamatan");
        $this->info("");
        
        // Create request object
        $request = new Request();
        $request->merge([
            'kabupaten' => $kabupaten,
            'kecamatan' => $kecamatan,
            'skip' => 0,
            'limit' => 10000
        ]);
        
        // Call the controller
        $controller = new BusinessController();
        $response = $controller->index($request);
        
        // Get the response data
        $data = $response->getData(true);
        
        $this->info("API Response:");
        $this->info("=============");
        $this->line("Total: " . $data['total']);
        $this->line("Count: " . $data['count']);
        $this->line("Skip: " . $data['skip']);
        $this->line("Limit: " . $data['limit']);
        
        $this->info("\nFirst 5 businesses:");
        $this->info("===================");
        foreach(array_slice($data['data'], 0, 5) as $business) {
            $this->line("Name: " . $business['name']);
            $this->line("Address: " . $business['address']);
            $this->line("Area: " . $business['area']);
            $this->line("---");
        }
        
        return 0;
    }
}
