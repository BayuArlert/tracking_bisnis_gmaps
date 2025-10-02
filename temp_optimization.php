        // Optimasi: Fokus hanya pada Kabupaten Tabanan untuk efisiensi cost
        $areas = [
            'Kabupaten Tabanan' => ['lat' => -8.450000, 'lng' => 115.150000],
        ];

        // Gunakan radius yang lebih besar untuk coverage maksimal
        $radius = $request->radius ?? 15000; // 15km untuk coverage luas
        
        // Filter area jika ada request spesifik
        if ($request->filled('area')) {
            $requestedArea = $request->area;
            $areas = array_filter($areas, function($coords, $areaName) use ($requestedArea) {
                return str_contains(strtolower($areaName), strtolower($requestedArea));
            }, ARRAY_FILTER_USE_BOTH);
        }
