<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BaliRegion;

class BaliRegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 9 Kabupaten di Bali dengan MULTIPLE scraping points untuk full coverage
        // Setiap kabupaten dibagi menjadi beberapa zone untuk memastikan tidak ada area yang terlewat
        
        // BADUNG - Kabupaten prioritas tertinggi, dibagi menjadi 6 zones untuk full coverage
        // Berdasarkan batas wilayah Google Maps yang mencakup seluruh area Badung
        $badungZones = [
            // Zone 1: Kuta & Seminyak (termasuk area timur sampai border Denpasar)
            ['name' => 'Badung - Kuta & Seminyak', 'center_lat' => -8.716667, 'center_lng' => 115.166667, 'search_radius' => 8000, 'priority' => 1],
            
            // Zone 2: Nusa Dua & Jimbaran (seluruh semenanjung selatan)
            ['name' => 'Badung - Nusa Dua & Jimbaran', 'center_lat' => -8.800000, 'center_lng' => 115.200000, 'search_radius' => 8000, 'priority' => 1],
            
            // Zone 3: Canggu & Berawa (diperluas untuk coverage utara Canggu)
            ['name' => 'Badung - Canggu & Berawa', 'center_lat' => -8.650000, 'center_lng' => 115.133333, 'search_radius' => 7000, 'priority' => 1],
            
            // Zone 4: Mengwi & Abiansemal (diperluas untuk coverage Mengwi yang lebih luas)
            ['name' => 'Badung - Mengwi & Abiansemal', 'center_lat' => -8.566667, 'center_lng' => 115.175000, 'search_radius' => 9000, 'priority' => 1],
            
            // Zone 5: Petang & Pegunungan (area utara Badung)
            ['name' => 'Badung - Petang & Pegunungan', 'center_lat' => -8.416667, 'center_lng' => 115.200000, 'search_radius' => 10000, 'priority' => 1],
            
            // Zone 6: Border Timur & Tengah (untuk memastikan tidak ada gap di border Denpasar)
            ['name' => 'Badung - Border Timur & Tengah', 'center_lat' => -8.666667, 'center_lng' => 115.200000, 'search_radius' => 8000, 'priority' => 1],
        ];
        
        // DENPASAR - Kota kecil, cukup 2 zone
        $denpasarZones = [
            ['name' => 'Denpasar - Selatan & Timur', 'center_lat' => -8.675000, 'center_lng' => 115.233333, 'search_radius' => 5000, 'priority' => 2],
            ['name' => 'Denpasar - Barat & Utara', 'center_lat' => -8.625000, 'center_lng' => 115.200000, 'search_radius' => 5000, 'priority' => 2],
        ];
        
        // GIANYAR - Dibagi 4 zone (termasuk Ubud)
        $gianyarZones = [
            ['name' => 'Gianyar - Ubud & Sekitar', 'center_lat' => -8.500000, 'center_lng' => 115.266667, 'search_radius' => 8000, 'priority' => 3],
            ['name' => 'Gianyar - Tegallalang & Payangan', 'center_lat' => -8.425000, 'center_lng' => 115.275000, 'search_radius' => 9000, 'priority' => 3],
            ['name' => 'Gianyar - Sukawati & Blahbatuh', 'center_lat' => -8.575000, 'center_lng' => 115.325000, 'search_radius' => 7000, 'priority' => 3],
            ['name' => 'Gianyar - Tampaksiring', 'center_lat' => -8.433333, 'center_lng' => 115.366667, 'search_radius' => 7000, 'priority' => 3],
        ];
        
        // TABANAN - Kabupaten luas, butuh 5 zone
        $tabananZones = [
            ['name' => 'Tabanan - Kota & Kediri', 'center_lat' => -8.465000, 'center_lng' => 115.145000, 'search_radius' => 7000, 'priority' => 4],
            ['name' => 'Tabanan - Selemadeg', 'center_lat' => -8.430000, 'center_lng' => 115.133333, 'search_radius' => 10000, 'priority' => 4],
            ['name' => 'Tabanan - Pantai (Tanah Lot)', 'center_lat' => -8.620833, 'center_lng' => 115.086667, 'search_radius' => 8000, 'priority' => 4],
            ['name' => 'Tabanan - Penebel & Baturiti', 'center_lat' => -8.445000, 'center_lng' => 115.185000, 'search_radius' => 9000, 'priority' => 4],
            ['name' => 'Tabanan - Pupuan & Pegunungan', 'center_lat' => -8.383333, 'center_lng' => 115.100000, 'search_radius' => 10000, 'priority' => 4],
        ];
        
        // BULELENG - Kabupaten terbesar, butuh 6 zone
        $bulelengZones = [
            ['name' => 'Buleleng - Singaraja Pusat', 'center_lat' => -8.116667, 'center_lng' => 115.083333, 'search_radius' => 7000, 'priority' => 5],
            ['name' => 'Buleleng - Lovina & Seririt', 'center_lat' => -8.150000, 'center_lng' => 115.016667, 'search_radius' => 10000, 'priority' => 5],
            ['name' => 'Buleleng - Gerokgak (Barat)', 'center_lat' => -8.200000, 'center_lng' => 114.916667, 'search_radius' => 12000, 'priority' => 5],
            ['name' => 'Buleleng - Sawan & Kubutambahan', 'center_lat' => -8.091667, 'center_lng' => 115.133333, 'search_radius' => 9000, 'priority' => 5],
            ['name' => 'Buleleng - Tejakula (Timur)', 'center_lat' => -8.141667, 'center_lng' => 115.300000, 'search_radius' => 10000, 'priority' => 5],
            ['name' => 'Buleleng - Busungbiu & Pegunungan', 'center_lat' => -8.250000, 'center_lng' => 115.050000, 'search_radius' => 11000, 'priority' => 5],
        ];
        
        // KLUNGKUNG - Termasuk Nusa Penida yang terpisah
        $klungkungZones = [
            ['name' => 'Klungkung - Daratan', 'center_lat' => -8.533333, 'center_lng' => 115.400000, 'search_radius' => 7000, 'priority' => 6],
            ['name' => 'Klungkung - Nusa Penida', 'center_lat' => -8.733333, 'center_lng' => 115.541667, 'search_radius' => 12000, 'priority' => 6],
            ['name' => 'Klungkung - Nusa Lembongan & Ceningan', 'center_lat' => -8.683333, 'center_lng' => 115.450000, 'search_radius' => 5000, 'priority' => 6],
        ];
        
        // BANGLI - Termasuk Kintamani di pegunungan
        $bangliZones = [
            ['name' => 'Bangli - Kota & Susut', 'center_lat' => -8.425000, 'center_lng' => 115.325000, 'search_radius' => 8000, 'priority' => 7],
            ['name' => 'Bangli - Kintamani & Danau Batur', 'center_lat' => -8.250000, 'center_lng' => 115.375000, 'search_radius' => 12000, 'priority' => 7],
            ['name' => 'Bangli - Tembuku', 'center_lat' => -8.483333, 'center_lng' => 115.366667, 'search_radius' => 7000, 'priority' => 7],
        ];
        
        // KARANGASEM - Kabupaten luas dengan pantai & gunung
        $karangasemZones = [
            ['name' => 'Karangasem - Amlapura & Manggis', 'center_lat' => -8.500000, 'center_lng' => 115.533333, 'search_radius' => 8000, 'priority' => 8],
            ['name' => 'Karangasem - Candidasa', 'center_lat' => -8.516667, 'center_lng' => 115.566667, 'search_radius' => 7000, 'priority' => 8],
            ['name' => 'Karangasem - Amed & Tulamben', 'center_lat' => -8.341667, 'center_lng' => 115.616667, 'search_radius' => 10000, 'priority' => 8],
            ['name' => 'Karangasem - Bebandem & Sidemen', 'center_lat' => -8.458333, 'center_lng' => 115.466667, 'search_radius' => 9000, 'priority' => 8],
            ['name' => 'Karangasem - Rendang & Gunung Agung', 'center_lat' => -8.366667, 'center_lng' => 115.433333, 'search_radius' => 10000, 'priority' => 8],
        ];
        
        // JEMBRANA - Kabupaten barat
        $jembranaZones = [
            ['name' => 'Jembrana - Negara Pusat', 'center_lat' => -8.350000, 'center_lng' => 114.616667, 'search_radius' => 8000, 'priority' => 9],
            ['name' => 'Jembrana - Pantai Barat (Medewi)', 'center_lat' => -8.466667, 'center_lng' => 114.933333, 'search_radius' => 10000, 'priority' => 9],
            ['name' => 'Jembrana - Pekutatan & Melaya', 'center_lat' => -8.383333, 'center_lng' => 114.766667, 'search_radius' => 9000, 'priority' => 9],
            ['name' => 'Jembrana - Mendoyo', 'center_lat' => -8.400000, 'center_lng' => 114.683333, 'search_radius' => 8000, 'priority' => 9],
        ];
        
        // Gabungkan semua zones
        $kabupaten = array_merge(
            $badungZones,
            $denpasarZones,
            $gianyarZones,
            $tabananZones,
            $bulelengZones,
            $klungkungZones,
            $bangliZones,
            $karangasemZones,
            $jembranaZones
        );

        foreach ($kabupaten as $data) {
            BaliRegion::create([
                'type' => 'kabupaten',
                'name' => $data['name'],
                'parent_id' => null,
                'center_lat' => $data['center_lat'],
                'center_lng' => $data['center_lng'],
                'search_radius' => $data['search_radius'],
                'priority' => $data['priority'],
            ]);
        }

        // Get kabupaten IDs for creating kecamatan
        $kabupatenIds = BaliRegion::where('type', 'kabupaten')->pluck('id', 'name');

        // Kecamatan untuk setiap kabupaten (sample - bisa ditambah lebih lengkap)
        $kecamatan = [
            // Badung
            'Badung' => [
                ['name' => 'Kuta', 'center_lat' => -8.716667, 'center_lng' => 115.166667],
                ['name' => 'Kuta Selatan', 'center_lat' => -8.800000, 'center_lng' => 115.116667],
                ['name' => 'Kuta Utara', 'center_lat' => -8.650000, 'center_lng' => 115.100000],
                ['name' => 'Mengwi', 'center_lat' => -8.583333, 'center_lng' => 115.150000],
                ['name' => 'Abiansemal', 'center_lat' => -8.550000, 'center_lng' => 115.200000],
                ['name' => 'Petang', 'center_lat' => -8.400000, 'center_lng' => 115.200000],
            ],
            // Denpasar
            'Denpasar' => [
                ['name' => 'Denpasar Selatan', 'center_lat' => -8.700000, 'center_lng' => 115.216667],
                ['name' => 'Denpasar Timur', 'center_lat' => -8.650000, 'center_lng' => 115.250000],
                ['name' => 'Denpasar Barat', 'center_lat' => -8.650000, 'center_lng' => 115.183333],
                ['name' => 'Denpasar Utara', 'center_lat' => -8.600000, 'center_lng' => 115.216667],
            ],
            // Gianyar
            'Gianyar' => [
                ['name' => 'Ubud', 'center_lat' => -8.500000, 'center_lng' => 115.266667],
                ['name' => 'Tegallalang', 'center_lat' => -8.450000, 'center_lng' => 115.300000],
                ['name' => 'Payangan', 'center_lat' => -8.400000, 'center_lng' => 115.250000],
                ['name' => 'Sukawati', 'center_lat' => -8.600000, 'center_lng' => 115.300000],
                ['name' => 'Blahbatuh', 'center_lat' => -8.550000, 'center_lng' => 115.350000],
                ['name' => 'Gianyar', 'center_lat' => -8.550000, 'center_lng' => 115.316667],
                ['name' => 'Tampaksiring', 'center_lat' => -8.450000, 'center_lng' => 115.350000],
            ],
            // Tabanan
            'Tabanan' => [
                ['name' => 'Tabanan', 'center_lat' => -8.450000, 'center_lng' => 115.150000],
                ['name' => 'Kediri', 'center_lat' => -8.480000, 'center_lng' => 115.140000],
                ['name' => 'Kerambitan', 'center_lat' => -8.470000, 'center_lng' => 115.120000],
                ['name' => 'Penebel', 'center_lat' => -8.430000, 'center_lng' => 115.180000],
                ['name' => 'Selemadeg', 'center_lat' => -8.420000, 'center_lng' => 115.160000],
                ['name' => 'Selemadeg Barat', 'center_lat' => -8.400000, 'center_lng' => 115.150000],
                ['name' => 'Selemadeg Timur', 'center_lat' => -8.440000, 'center_lng' => 115.170000],
                ['name' => 'Baturiti', 'center_lat' => -8.460000, 'center_lng' => 115.190000],
                ['name' => 'Margasari', 'center_lat' => -8.500000, 'center_lng' => 115.130000],
                ['name' => 'Pupuan', 'center_lat' => -8.400000, 'center_lng' => 115.100000],
            ],
            // Buleleng
            'Buleleng' => [
                ['name' => 'Singaraja', 'center_lat' => -8.116667, 'center_lng' => 115.083333],
                ['name' => 'Buleleng', 'center_lat' => -8.150000, 'center_lng' => 115.100000],
                ['name' => 'Sawan', 'center_lat' => -8.100000, 'center_lng' => 115.150000],
                ['name' => 'Kubutambahan', 'center_lat' => -8.083333, 'center_lng' => 115.116667],
                ['name' => 'Tejakula', 'center_lat' => -8.050000, 'center_lng' => 115.200000],
                ['name' => 'Sukasada', 'center_lat' => -8.200000, 'center_lng' => 115.083333],
                ['name' => 'Busungbiu', 'center_lat' => -8.250000, 'center_lng' => 115.050000],
                ['name' => 'Gerokgak', 'center_lat' => -8.200000, 'center_lng' => 114.950000],
                ['name' => 'Seririt', 'center_lat' => -8.150000, 'center_lng' => 114.950000],
                ['name' => 'Banjar', 'center_lat' => -8.100000, 'center_lng' => 115.000000],
            ],
            // Klungkung
            'Klungkung' => [
                ['name' => 'Klungkung', 'center_lat' => -8.533333, 'center_lng' => 115.400000],
                ['name' => 'Banjarangkan', 'center_lat' => -8.500000, 'center_lng' => 115.350000],
                ['name' => 'Dawan', 'center_lat' => -8.550000, 'center_lng' => 115.400000],
                ['name' => 'Nusa Penida', 'center_lat' => -8.750000, 'center_lng' => 115.550000],
            ],
            // Bangli
            'Bangli' => [
                ['name' => 'Bangli', 'center_lat' => -8.450000, 'center_lng' => 115.350000],
                ['name' => 'Susut', 'center_lat' => -8.400000, 'center_lng' => 115.300000],
                ['name' => 'Tembuku', 'center_lat' => -8.500000, 'center_lng' => 115.350000],
                ['name' => 'Kintamani', 'center_lat' => -8.300000, 'center_lng' => 115.350000],
            ],
            // Karangasem
            'Karangasem' => [
                ['name' => 'Amlapura', 'center_lat' => -8.450000, 'center_lng' => 115.516667],
                ['name' => 'Abang', 'center_lat' => -8.400000, 'center_lng' => 115.450000],
                ['name' => 'Bebandem', 'center_lat' => -8.500000, 'center_lng' => 115.500000],
                ['name' => 'Karangasem', 'center_lat' => -8.450000, 'center_lng' => 115.516667],
                ['name' => 'Kubu', 'center_lat' => -8.350000, 'center_lng' => 115.550000],
                ['name' => 'Manggis', 'center_lat' => -8.550000, 'center_lng' => 115.550000],
                ['name' => 'Rendang', 'center_lat' => -8.400000, 'center_lng' => 115.400000],
                ['name' => 'Selat', 'center_lat' => -8.500000, 'center_lng' => 115.600000],
                ['name' => 'Sidemen', 'center_lat' => -8.450000, 'center_lng' => 115.450000],
            ],
            // Jembrana
            'Jembrana' => [
                ['name' => 'Negara', 'center_lat' => -8.350000, 'center_lng' => 114.666667],
                ['name' => 'Jembrana', 'center_lat' => -8.300000, 'center_lng' => 114.600000],
                ['name' => 'Mendoyo', 'center_lat' => -8.400000, 'center_lng' => 114.700000],
                ['name' => 'Pekutatan', 'center_lat' => -8.450000, 'center_lng' => 114.800000],
                ['name' => 'Melaya', 'center_lat' => -8.250000, 'center_lng' => 114.750000],
            ],
        ];

        foreach ($kecamatan as $kabupatenName => $kecamatanList) {
            $kabupatenId = $kabupatenIds[$kabupatenName];
            
            foreach ($kecamatanList as $kecamatanData) {
                BaliRegion::create([
                    'type' => 'kecamatan',
                    'name' => $kecamatanData['name'],
                    'parent_id' => $kabupatenId,
                    'center_lat' => $kecamatanData['center_lat'],
                    'center_lng' => $kecamatanData['center_lng'],
                    'search_radius' => 3000, // Kecamatan radius lebih kecil
                    'priority' => 1,
                ]);
            }
        }
    }
}
