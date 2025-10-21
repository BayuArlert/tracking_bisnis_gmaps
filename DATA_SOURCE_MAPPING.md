# 📊 Data Source Mapping - Database vs Hardcoded

## ✅ SEMUA DATA SEKARANG DARI DATABASE!

Sistem sekarang **100% database-driven** untuk semua filter options. Tidak ada hardcoded data kecuali fallback jika API error.

---

## 📍 **1. HIERARCHICAL LOCATION FILTER**

### **Kabupaten** ✅ Database
**Component**: `HierarchicalLocationFilter.tsx`

```tsx
// Fetch dari database
const response = await axios.get(`${API}/regions/kabupaten`);
```

**Backend**: `RegionController@getKabupaten`
```php
BaliRegion::kabupaten()
    ->orderBy('priority')
    ->get(['id', 'name', 'priority']);
```

**Database Table**: `bali_regions`
```sql
SELECT id, name, priority 
FROM bali_regions 
WHERE type = 'kabupaten' 
ORDER BY priority
```

**Data Source**: `BaliRegionSeeder.php`
- 41 zones covering all 9 kabupaten di Bali
- Badung: 6 zones
- Denpasar: 2 zones
- Gianyar: 4 zones
- Tabanan: 5 zones
- Buleleng: 6 zones
- Klungkung: 3 zones
- Bangli: 3 zones
- Karangasem: 5 zones
- Jembrana: 4 zones

---

### **Kecamatan** ✅ Database (Dynamic based on Kabupaten)
**Component**: `HierarchicalLocationFilter.tsx`

```tsx
// Auto-fetch when kabupaten changes
const response = await axios.get(
  `${API}/regions/kecamatan/${kabupatenName}`
);
```

**Backend**: `RegionController@getKecamatan`
```php
BaliRegion::kecamatan()
    ->whereHas('parent', function($query) use ($kabupatenName) {
        $query->where('name', 'LIKE', $kabupatenName . '%');
    })
    ->orderBy('name')
    ->get();
```

**Database Table**: `bali_regions`
```sql
SELECT id, name, parent_id 
FROM bali_regions 
WHERE type = 'kecamatan' 
  AND parent_id IN (
    SELECT id FROM bali_regions 
    WHERE type = 'kabupaten' 
      AND name LIKE 'Badung%'
  )
ORDER BY name
```

**Data Source**: `BaliRegionSeeder.php`
- Badung: 6 kecamatan (Kuta, Kuta Selatan, Kuta Utara, Mengwi, Abiansemal, Petang)
- Denpasar: 4 kecamatan
- Gianyar: 7 kecamatan (including Ubud, Tegallalang)
- Tabanan: 10 kecamatan
- Buleleng: 10 kecamatan
- Klungkung: 4 kecamatan
- Bangli: 4 kecamatan
- Karangasem: 9 kecamatan
- Jembrana: 5 kecamatan

**Total**: ~59 kecamatan di database

---

### **Desa** ⏳ Database (Ready but no data yet)
**Component**: `HierarchicalLocationFilter.tsx`

```tsx
// Future implementation - structure ready
const response = await axios.get(
  `${API}/regions/desa/${kecamatanId}`
);
```

**Backend**: `RegionController@getDesa`
```php
BaliRegion::desa()
    ->where('parent_id', $kecamatanId)
    ->orderBy('name')
    ->get();
```

**Status**: 
- ✅ API endpoint ready
- ✅ Database structure ready  
- ⏳ Data not in seeder yet (can be added anytime)

---

## 🏢 **2. CATEGORY FILTER**

### **Categories** ✅ Database
**Component**: `CategoryMultiSelect.tsx`

```tsx
// Fetch dari database
const response = await axios.get(`${API}/scrape/categories`);
```

**Backend**: `ScrapeController@categories`
```php
CategoryMapping::pluck('brief_category');
```

**Database Table**: `category_mappings`
```sql
SELECT brief_category 
FROM category_mappings
```

**Data Source**: `CategoryMappingSeeder.php`
- Café
- Restoran
- Sekolah
- Villa
- Hotel
- Popular Spot
- Lainnya

**Total**: 7 categories

**With Full Metadata**:
```php
// Each category has:
- brief_category (for display)
- google_types (array: cafe, restaurant, etc)
- keywords_id (array: Indonesian keywords)
- keywords_en (array: English keywords)
- text_search_queries (array: optimized search queries)
```

---

## 📅 **3. PERIOD FILTER**

### **Period Presets** ⚙️ Static (By Design)
**Component**: `PeriodFilter.tsx`

```tsx
// Hardcoded presets (business logic, not data)
const presets = [30, 60, 90, 180];
```

**Reason**: Business logic, bukan data yang berubah
- 30 hari = 1 bulan
- 60 hari = 2 bulan
- 90 hari = 3 bulan (quarter)
- 180 hari = 6 bulan (semester)

**Plus Custom Date Range**: User bisa pilih tanggal arbitrary

---

## 🎯 **4. CONFIDENCE THRESHOLD**

### **Threshold Range** ⚙️ Static (By Design)
**Component**: `ConfidenceSlider.tsx`

```tsx
// Range 0-100 (business logic)
min={0}
max={100}
step={5}
```

**Reason**: Confidence score selalu 0-100%, bukan data dari database

---

## 📊 **5. DATA AGE FILTER**

### **Age Categories** ⚙️ Static (By Design)
**Component**: Existing Select in BusinessList.tsx

```tsx
const ageCategories = [
  'ultra_new',  // < 1 minggu
  'very_new',   // < 1 bulan
  'new',        // < 3 bulan
  'recent',     // < 12 bulan
  'established',// 1-3 tahun
  'old'         // > 3 tahun
];
```

**Reason**: Business logic untuk age classification, bukan data yang berubah

---

## 🗺️ **BUSINESS DATA**

### **Businesses** ✅ Database (100%)
**Source**: `businesses` table

All business data fetched from database:
- Basic Info: name, category, area, address
- Location: lat, lng, google_maps_url
- Metrics: rating, review_count, first_seen
- Indicators: 11 detection signals (JSON field)
- Metadata: review timeline, photo activity, confidence score

**API**: `BusinessController@index`
```php
Business::query()
    ->where('area', 'LIKE', '%' . $kabupaten . '%')
    ->where('area', 'LIKE', '%' . $kecamatan . '%')
    ->whereIn('category', $categories)
    ->where('first_seen', '>=', $dateFrom)
    ->whereJsonContains('indicators->new_business_confidence', '>=' . $threshold)
    ->get();
```

---

## 📈 **ANALYTICS DATA**

### **Hot Zones** ✅ Database (Calculated)
**Component**: `Top5HotZones.tsx`

```tsx
const response = await axios.get(`${API}/analytics/hot-zones`);
```

**Backend**: `StatisticsController@hotZones`
```php
// Calculate from businesses table
Business::where('first_seen', '>=', $dateFrom)
    ->groupBy(['kabupaten', 'kecamatan'])
    ->selectRaw('COUNT(*) as count, kabupaten, kecamatan')
    ->orderBy('count', 'desc')
    ->limit(5)
    ->get();
```

---

## 🔄 **DATA FLOW DIAGRAM**

```
┌─────────────────────────────────────┐
│  DATABASE (Source of Truth)         │
├─────────────────────────────────────┤
│  1. bali_regions (41 zones)        │
│     - Kabupaten (9)                 │
│     - Kecamatan (59)                │
│     - Desa (0 - future)             │
│                                     │
│  2. category_mappings (7)           │
│     - Café, Restoran, Sekolah, etc  │
│                                     │
│  3. businesses (dynamic)            │
│     - All business data             │
│     - 11 detection indicators       │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  BACKEND API (Controllers)          │
├─────────────────────────────────────┤
│  RegionController                   │
│  ├─ /api/regions/kabupaten         │
│  ├─ /api/regions/kecamatan/{name}  │
│  └─ /api/regions/desa/{id}         │
│                                     │
│  ScrapeController                   │
│  └─ /api/scrape/categories         │
│                                     │
│  BusinessController                 │
│  └─ /api/businesses (with filters) │
│                                     │
│  StatisticsController               │
│  └─ /api/analytics/hot-zones       │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  FRONTEND COMPONENTS                │
├─────────────────────────────────────┤
│  HierarchicalLocationFilter         │
│  ├─ Fetch kabupaten on mount       │
│  └─ Fetch kecamatan on kabupaten   │
│      change                         │
│                                     │
│  CategoryMultiSelect                │
│  └─ Fetch categories on mount      │
│                                     │
│  BusinessList                       │
│  └─ Fetch businesses with filters  │
│                                     │
│  Top5HotZones                       │
│  └─ Fetch hot zones analytics      │
└─────────────────────────────────────┘
```

---

## 🎯 **SUMMARY**

### **Data dari Database** (Dynamic):
✅ **Kabupaten** - BaliRegion table (type='kabupaten')
✅ **Kecamatan** - BaliRegion table (type='kecamatan')  
⏳ **Desa** - BaliRegion table (type='desa') - ready but no data yet
✅ **Categories** - CategoryMapping table (brief_category field)
✅ **Businesses** - Business table (all data)
✅ **Hot Zones** - Calculated from Business table

### **Static Business Logic** (By Design):
⚙️ **Period Presets** - [30, 60, 90, 180] days (business logic)
⚙️ **Confidence Range** - 0-100% (scoring logic)
⚙️ **Age Categories** - [ultra_new, very_new, new, etc] (classification logic)

### **Fallback**:
All components have fallback values jika API error, tapi **primary source selalu database**.

---

## 🔄 **How to Update Data**

### **Add New Kabupaten/Kecamatan**:
```bash
# Edit seeder
vim database/seeders/BaliRegionSeeder.php

# Add new entry
$newZones = [
    ['name' => 'New Zone', 'center_lat' => -8.5, 'center_lng' => 115.2, ...]
];

# Run seeder
php artisan db:seed --class=BaliRegionSeeder
```

### **Add New Category**:
```bash
# Edit seeder
vim database/seeders/CategoryMappingSeeder.php

# Add new category
$categories[] = [
    'brief_category' => 'Gym',
    'google_types' => ['gym', 'fitness_center'],
    ...
];

# Run seeder
php artisan db:seed --class=CategoryMappingSeeder
```

### **Add Desa Data** (Future):
```php
// In BaliRegionSeeder.php, add:
$desa = [
    'Kuta Utara' => [
        ['name' => 'Canggu', 'center_lat' => -8.650, ...],
        ['name' => 'Berawa', 'center_lat' => -8.645, ...],
        ['name' => 'Tibubeneng', 'center_lat' => -8.655, ...],
    ],
];

foreach ($desa as $kecamatanName => $desaList) {
    $kecamatanId = BaliRegion::where('name', $kecamatanName)
        ->where('type', 'kecamatan')
        ->first()->id;
    
    foreach ($desaList as $desaData) {
        BaliRegion::create([
            'type' => 'desa',
            'name' => $desaData['name'],
            'parent_id' => $kecamatanId,
            ...
        ]);
    }
}
```

---

## ✅ **KESIMPULAN**

| Filter | Source | Type | Dynamic? |
|--------|--------|------|----------|
| **Kabupaten** | `bali_regions` table | Database | ✅ Yes |
| **Kecamatan** | `bali_regions` table | Database | ✅ Yes |
| **Desa** | `bali_regions` table | Database | ⏳ Ready (no data) |
| **Categories** | `category_mappings` table | Database | ✅ Yes |
| **Period Presets** | Component logic | Static | ⚙️ By design |
| **Confidence Range** | Scoring logic | Static | ⚙️ By design |
| **Businesses** | `businesses` table | Database | ✅ Yes |
| **Hot Zones** | Calculated from `businesses` | Database | ✅ Yes |

---

## 🚀 **Keuntungan Database-Driven**

1. ✅ **Easy to Update**: Tambah kabupaten/kategori baru via seeder
2. ✅ **Consistent**: Frontend selalu sync dengan backend
3. ✅ **Scalable**: Bisa tambah data tanpa ubah code
4. ✅ **Flexible**: Bisa tambah metadata (priority, icons, descriptions)
5. ✅ **No Hardcoded Data**: Semua dari single source of truth

---

## 📝 **Example: Adding New Kabupaten Zone**

```php
// database/seeders/BaliRegionSeeder.php

$newZone = [
    'name' => 'Badung - Uluwatu & Pecatu',
    'center_lat' => -8.850000,
    'center_lng' => 115.150000,
    'search_radius' => 8000,
    'priority' => 1
];

BaliRegion::create([
    'type' => 'kabupaten',
    ...$newZone
]);
```

Run: `php artisan db:seed --class=BaliRegionSeeder`

Frontend akan automatically detect dan show di dropdown! ✨

---

**Sistem sekarang 100% database-driven dan production-ready!** 🎉

