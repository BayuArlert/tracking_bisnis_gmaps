# Testing Fetch Cafe di Kabupaten Tabanan

## Cara Menggunakan API Fetch

### Method 1: Text Search (Rekomendasi untuk kategori + area spesifik)

**URL:** `GET /api/businesses/new?category=cafe&area=Kabupaten Tabanan`

**Atau dengan POST/GET parameters:**
```bash
# Via Browser atau Postman
http://localhost:8000/api/businesses/new?category=cafe&area=Kabupaten%20Tabanan

# Via cURL
curl "http://localhost:8000/api/businesses/new?category=cafe&area=Kabupaten%20Tabanan"
```

**Response Example:**
```json
{
  "method": "text_search",
  "query": "cafe in Kabupaten Tabanan, Bali, Indonesia",
  "fetched": 45,
  "new": 12,
  "total_processed": 60,
  "pages_fetched": 3,
  "skipped_wrong_area": 8,
  "skipped_wrong_category": 7,
  "businesses": [
    {
      "id": 123,
      "name": "Kopi Bali Cafe",
      "category": "cafe",
      "area": "Kabupaten Tabanan",
      "address": "Jl. Raya Tabanan, Bali",
      "rating": 4.5,
      "review_count": 120,
      ...
    }
  ]
}
```

### Method 2: Nearby Search (Untuk multiple areas)

**URL:** `GET /api/businesses/new?area=Tabanan&category=cafe&radius=10000`

## Validasi yang Diterapkan

### 1. Validasi Kategori
- ✅ Cek apakah `types` dari Google Maps mengandung kategori yang diminta
- ✅ Case-insensitive matching
- ✅ Support untuk format: `cafe`, `Cafe`, `CAFE`
- ❌ Skip bisnis yang tidak match kategori

### 2. Validasi Area
- ✅ Cek apakah address mengandung nama area yang diminta
- ✅ Case-insensitive matching
- ✅ Support untuk format: `Kabupaten Tabanan`, `tabanan`, `TABANAN`
- ❌ Skip bisnis yang tidak di area yang diminta

### 3. Validasi Data
- ✅ Validasi field wajib (name, address, coordinates)
- ✅ Cek duplikasi berdasarkan nama, alamat, dan koordinat
- ✅ Generate indicators (recently_opened, few_reviews, dll)

## Kategori yang Didukung

Google Places API menggunakan type codes. Berikut beberapa yang umum:

- `cafe` - Cafe / Kafe
- `restaurant` - Restoran
- `bar` - Bar
- `bakery` - Toko Roti
- `food` - Tempat Makan umum
- `store` - Toko
- `lodging` - Penginapan
- `beauty_salon` - Salon Kecantikan
- `gym` - Gym / Fitness
- `spa` - Spa
- `hospital` - Rumah Sakit
- `doctor` - Dokter
- `pharmacy` - Apotek
- `school` - Sekolah
- `university` - Universitas
- `bank` - Bank
- `atm` - ATM
- `gas_station` - SPBU

## Area yang Didukung

- Kabupaten Badung
- Kabupaten Bangli
- Kabupaten Buleleng
- Kabupaten Gianyar
- Kabupaten Jembrana
- Kabupaten Karangasem
- Kabupaten Klungkung
- **Kabupaten Tabanan** ✅
- Kota Denpasar

## Testing

### Test 1: Fetch Cafe di Kabupaten Tabanan
```bash
curl "http://localhost:8000/api/businesses/new?category=cafe&area=Kabupaten%20Tabanan"
```

### Test 2: Fetch Restaurant di Kota Denpasar
```bash
curl "http://localhost:8000/api/businesses/new?category=restaurant&area=Denpasar"
```

### Test 3: Fetch Bar di Kabupaten Badung
```bash
curl "http://localhost:8000/api/businesses/new?category=bar&area=Badung"
```

## Monitoring

Semua aktivitas fetch akan dicatat di log file:
- `storage/logs/laravel.log`

Cari log dengan keyword:
- `Starting Text Search for:` - Mulai fetch
- `Fetched page` - Progress pagination
- `✓ Saved:` - Bisnis berhasil disimpan
- `Skipping` - Bisnis di-skip (dengan alasan)
- `Fetch completed` - Summary hasil fetch

## Expected Behavior

1. **Query ke Google:** `"cafe in Kabupaten Tabanan, Bali, Indonesia"`
2. **Google returns:** ~20-60 results (bisa lebih dengan pagination)
3. **Filter Category:** Hanya ambil yang types-nya mengandung `cafe`
4. **Filter Area:** Hanya ambil yang address-nya mengandung `Kabupaten Tabanan` atau `Tabanan`
5. **Save to DB:** Semua yang lolos filter disimpan
6. **Return:** Semua bisnis yang baru atau recently_opened

## Troubleshooting

### Masalah: Tidak ada hasil / ZERO_RESULTS
- Pastikan API Key valid dan aktif
- Cek apakah ada limit quota di Google Cloud Console
- Coba query manual di Google Maps untuk verifikasi data memang ada

### Masalah: Hasil terlalu banyak di-skip
- Cek log untuk melihat alasan skip
- Mungkin Google return hasil di area lain (normal behavior)
- Filter akan memastikan hanya yang sesuai yang disimpan

### Masalah: Kategori tidak match
- Google Places menggunakan type codes
- Coba pakai type code yang lebih umum (e.g., `food` untuk semua tempat makan)
- Lihat dokumentasi Google Places Types: https://developers.google.com/maps/documentation/places/web-service/supported_types

