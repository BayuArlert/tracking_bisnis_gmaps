# Business Monitoring Dashboard

Aplikasi dashboard untuk memantau penambahan bisnis baru (resto, hotel, gym) di 1 Kota dengan data dari Google Maps / Google Places API.

## Fitur Utama

### ✅ Fitur yang Sudah Diimplementasi

1. **Dashboard Utama**
   - Statistik bisnis baru mingguan/bulanan
   - Indikator bisnis baru (recently opened, review spike, dll)
   - Filter berdasarkan kategori dan area
   - Export CSV dan notifikasi email otomatis

2. **Halaman Statistik**
   - Grafik tren mingguan/bulanan
   - Analisis per kategori dan area
   - Google Maps heatmap dengan markers
   - Top 10 bisnis baru dengan review terbanyak

3. **Daftar Bisnis**
   - Filter berdasarkan kategori, area, tanggal muncul, jumlah review
   - Radius pencarian yang bisa diatur (1-50km)
   - Pagination dan search
   - Export CSV

4. **Google Maps Integration**
   - Heatmap lokasi bisnis baru
   - Markers dengan kategori berbeda
   - Info window dengan detail bisnis
   - Toggle heatmap dan markers

5. **Notifikasi & Export**
   - Email summary mingguan/bulanan
   - CSV export dengan data lengkap
   - Scheduling notifikasi otomatis
   - HTML email template

## Setup Aplikasi

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 2. Environment Configuration

Copy `.env.example` ke `.env` dan konfigurasi:

```bash
cp .env.example .env
```

Edit file `.env` dan tambahkan:

```env
# Google Maps API Key
REACT_APP_GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=business_monitoring
DB_USERNAME=root
DB_PASSWORD=

# Mail Configuration (untuk notifikasi)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Business Monitoring Dashboard"
```

### 3. Database Setup

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Optional: Seed with sample data
php artisan db:seed
```

### 4. Build Frontend

```bash
# Development
npm run dev

# Production
npm run build
```

### 5. Start Application

```bash
# Start Laravel server
php artisan serve

# Start Vite dev server (jika development)
npm run dev
```

## Google Maps API Setup

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau pilih existing project
3. Enable APIs:
   - Maps JavaScript API
   - Places API
   - Geocoding API
4. Buat API Key dan restrict untuk domain Anda
5. Tambahkan API Key ke file `.env`

## Struktur Database

### Tabel `businesses`

```sql
- id (primary key)
- place_id (unique, dari Google Places)
- name
- category
- address
- area
- lat, lng (koordinat)
- rating
- review_count
- first_seen (tanggal pertama kali ditemukan)
- last_fetched
- indicators (JSON: recently_opened, review_spike, dll)
- timestamps
```

## API Endpoints

### Dashboard
- `GET /api/dashboard/stats` - Statistik dashboard

### Business Management
- `GET /api/businesses` - List bisnis dengan filter
- `GET /api/businesses/new` - Fetch data baru dari Google Places
- `GET /api/businesses/filter-options` - Opsi filter
- `GET /api/businesses/update-metadata` - Update metadata
- `GET /api/export/csv` - Export CSV

### Statistics
- `GET /api/statistics` - Data statistik
- `GET /api/statistics/heatmap` - Data untuk heatmap

### Notifications
- `POST /api/notifications/weekly-summary` - Kirim summary mingguan
- `POST /api/notifications/monthly-summary` - Kirim summary bulanan
- `POST /api/notifications/schedule` - Schedule notifikasi

## Kriteria Identifikasi "Bisnis Baru"

Sistem menandai bisnis baru berdasarkan beberapa indikator:

1. **Lonjakan review** dalam 1–2 bulan terakhir (>10 review baru)
2. **Label "Recently opened"** di Google Business Profile
3. **Foto pertama** di-upload <3 bulan terakhir
4. **Tanggal listing** pertama kali muncul dalam database internal
5. **Rating masih sedikit** (<10–20 review total)
6. **Alamat/kategori unik** baru muncul di area itu
7. **Frekuensi check-in** mulai muncul

## Fitur Monitoring

### Area & Kategori Monitoring
- Pilih area (Yogyakarta, Sleman, Bantul, dll)
- Pilih kategori (resto, hotel, gym, dll)
- Radius pencarian bisa diatur (1-50 km)

### Dashboard Data
- Daftar bisnis baru mingguan/bulanan
- Filter berdasarkan kategori, area, tanggal muncul, jumlah review
- Status indikator dengan confidence score

### Tren & Statistik
- Grafik jumlah bisnis baru per minggu/bulan per kategori
- Heatmap lokasi bisnis baru di Google Maps
- Top 10 bisnis baru dengan review terbanyak bulan ini

## Output yang Diharapkan

✅ **Web Dashboard** dengan login sederhana (admin)
✅ **Halaman utama**: ringkasan jumlah bisnis baru minggu ini per area & kategori
✅ **Halaman detail**: list bisnis baru dengan indikator lengkap
✅ **Halaman statistik**: grafik tren, heatmap lokasi
✅ **Export & notif**: bisa unduh CSV dan kirim ringkasan otomatis

## Teknologi yang Digunakan

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: React 18, TypeScript, Inertia.js
- **UI**: Tailwind CSS, shadcn/ui components
- **Maps**: Google Maps JavaScript API
- **Database**: MySQL
- **Build Tool**: Vite

## Kontribusi

1. Fork repository
2. Buat feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.
