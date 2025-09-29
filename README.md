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

Edit file `.env` dan konfigurasi sesuai kebutuhan:

```env
# Application
APP_NAME="Business Monitoring Dashboard"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=business_monitoring
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Google Maps API Key
VITE_GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here

# Frontend URL (untuk CORS)
FRONTEND_URL=http://localhost:3000

# Mail Configuration (untuk notifikasi)
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Business Monitoring Dashboard"

# Session & Security
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
BCRYPT_ROUNDS=12
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
4. Buat API Key dan **PENTING**: restrict untuk domain Anda
5. Tambahkan API Key ke file `.env` sebagai `VITE_GOOGLE_MAPS_API_KEY`

### 🔒 Security Best Practices untuk API Key:
- Restrict API Key dengan HTTP referrers: `yourdomain.com/*`
- Set API restrictions hanya untuk Maps JavaScript API dan Places API
- Jangan commit API Key ke repository
- Gunakan environment variables

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
- Pilih area (Bali, Denpasar, Badung, Gianyar, Ubud, dll)
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

## 🔒 Security Features

### Authentication & Authorization
- Laravel Sanctum untuk API authentication
- Token-based authentication
- Rate limiting pada login/register (5 attempts per minute)
- Password hashing dengan bcrypt (12 rounds)

### API Security
- CORS protection dengan allowed origins terbatas
- Input validation pada semua endpoints
- SQL injection protection dengan Eloquent ORM
- XSS protection dengan React's built-in sanitization

### Environment Security
- Environment variables untuk sensitive data
- API keys tidak di-commit ke repository
- Debug mode bisa di-disable untuk production

## Output yang Diharapkan

✅ **Web Dashboard** dengan login sederhana (admin)
✅ **Halaman utama**: ringkasan jumlah bisnis baru minggu ini per area & kategori
✅ **Halaman detail**: list bisnis baru dengan indikator lengkap
✅ **Halaman statistik**: grafik tren, heatmap lokasi
✅ **Export & notif**: bisa unduh CSV dan kirim ringkasan otomatis

## Teknologi yang Digunakan

- **Backend**: Laravel 11, PHP 8.4+
- **Frontend**: React 19, TypeScript, Inertia.js
- **UI**: Tailwind CSS, shadcn/ui components
- **Maps**: Google Maps JavaScript API (Advanced Markers)
- **Database**: MySQL
- **Build Tool**: Vite
- **Authentication**: Laravel Sanctum
- **Testing**: PHPUnit

## License

Distributed under the MIT License. See `LICENSE` for more information.
