<?php

namespace App\Services;

class LocationParserService
{
    /**
     * Parse full location hierarchy from address string
     * 
     * @param string $address Google Maps formatted address
     * @return array ['kabupaten' => string|null, 'kecamatan' => string|null, 'desa' => string|null]
     * 
     * Example:
     * Input: "Jl. Raya Canggu, Canggu, Kec. Kuta Utara, Kabupaten Badung, Bali 80361"
     * Output: ['kabupaten' => 'Badung', 'kecamatan' => 'Kuta Utara', 'desa' => 'Canggu']
     */
    public function parseLocationHierarchy(string $address): array
    {
        if (empty($address)) {
            return [
                'kabupaten' => null,
                'kecamatan' => null,
                'desa' => null,
            ];
        }

        return [
            'kabupaten' => $this->extractKabupaten($address),
            'kecamatan' => $this->extractKecamatan($address),
            'desa' => $this->extractDesa($address),
        ];
    }

    /**
     * Extract kabupaten from address
     * Matches: "Kabupaten X", "Kota X", "Kab. X", "Kota X"
     */
    private function extractKabupaten(string $address): ?string
    {
        // Pattern 1: "Kabupaten X" or "Kota X"
        if (preg_match('/(?:Kabupaten|Kota)\s+([^,\d]+)/i', $address, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 2: "Kab. X" or "Kota X" (abbreviated)
        if (preg_match('/(?:Kab\.|Kota)\s+([^,\d]+)/i', $address, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 3: Look for common kabupaten names without prefix
        $commonKabupaten = [
            'Badung', 'Tabanan', 'Gianyar', 'Klungkung', 'Bangli', 
            'Karangasem', 'Jembrana', 'Buleleng', 'Denpasar'
        ];

        foreach ($commonKabupaten as $kabupaten) {
            if (stripos($address, $kabupaten) !== false) {
                return $kabupaten;
            }
        }

        return null;
    }

    /**
     * Extract kecamatan from address
     * Matches: "Kec. X", "Kecamatan X", "Kec X"
     */
    private function extractKecamatan(string $address): ?string
    {
        // Pattern 1: "Kecamatan X"
        if (preg_match('/Kecamatan\s+([^,\d]+)/i', $address, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 2: "Kec. X" or "Kec X"
        if (preg_match('/Kec\.?\s+([^,\d]+)/i', $address, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract desa/kelurahan from address
     * Strategy: Find the word/phrase that appears before "Kec." or "Kecamatan"
     */
    private function extractDesa(string $address): ?string
    {
        // Split address by commas for easier parsing
        $parts = array_map('trim', explode(',', $address));
        
        // Look for kecamatan pattern in each part
        foreach ($parts as $index => $part) {
            if (preg_match('/Kec\.?\s+/i', $part) || preg_match('/Kecamatan\s+/i', $part)) {
                // Found kecamatan, desa should be the previous part
                if ($index > 0) {
                    $desaCandidate = $parts[$index - 1];
                    
                    // Clean up the desa name
                    $desaCandidate = $this->cleanDesaName($desaCandidate);
                    
                    // Validate it's not empty and not a street name
                    if (!empty($desaCandidate) && !$this->isStreetName($desaCandidate)) {
                        return $desaCandidate;
                    }
                }
            }
        }

        // Fallback: Look for common desa patterns
        return $this->extractDesaFallback($address);
    }

    /**
     * Clean desa name by removing common prefixes and suffixes
     */
    private function cleanDesaName(string $name): string
    {
        // Remove common prefixes
        $name = preg_replace('/^(Desa|Kel\.?|Kelurahan)\s+/i', '', $name);
        
        // Remove common suffixes
        $name = preg_replace('/\s+(Desa|Kel\.?|Kelurahan)$/i', '', $name);
        
        // Remove postal codes
        $name = preg_replace('/\s+\d{5}$/', '', $name);
        
        // Remove province names
        $name = preg_replace('/\s+Bali$/', '', $name);
        
        return trim($name);
    }

    /**
     * Check if a string looks like a street name
     */
    private function isStreetName(string $name): bool
    {
        $streetPatterns = [
            '/^Jl\.?\s+/i',           // Jl. or Jalan
            '/^Jalan\s+/i',           // Jalan
            '/^Gg\.?\s+/i',          // Gang
            '/^Gang\s+/i',           // Gang
            '/^Komplek\s+/i',        // Komplek
            '/^Perum\s+/i',          // Perumahan
            '/^Blok\s+/i',           // Blok
            '/^RT\s+/i',             // RT
            '/^RW\s+/i',             // RW
        ];

        foreach ($streetPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback method to extract desa when main method fails
     */
    private function extractDesaFallback(string $address): ?string
    {
        // Look for common desa names in Bali
        $commonDesa = [
            'Canggu', 'Seminyak', 'Kuta', 'Legian', 'Sanur', 'Ubud', 
            'Jimbaran', 'Nusa Dua', 'Denpasar', 'Kerobokan', 'Tuban',
            'Petitenget', 'Echo Beach', 'Batu Bolong', 'Berawa'
        ];

        foreach ($commonDesa as $desa) {
            if (stripos($address, $desa) !== false) {
                return $desa;
            }
        }

        return null;
    }

    /**
     * Validate if parsed location data is consistent
     */
    public function validateLocationData(array $locationData): array
    {
        $issues = [];

        if (empty($locationData['kabupaten'])) {
            $issues[] = 'Kabupaten not found';
        }

        if (empty($locationData['kecamatan'])) {
            $issues[] = 'Kecamatan not found';
        }

        if (empty($locationData['desa'])) {
            $issues[] = 'Desa not found';
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'confidence' => $this->calculateConfidence($locationData),
        ];
    }

    /**
     * Calculate confidence score for parsed location data
     */
    private function calculateConfidence(array $locationData): int
    {
        $score = 0;

        if (!empty($locationData['kabupaten'])) $score += 40;
        if (!empty($locationData['kecamatan'])) $score += 35;
        if (!empty($locationData['desa'])) $score += 25;

        return $score;
    }
}
