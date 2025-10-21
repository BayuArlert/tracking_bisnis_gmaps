# 🚀 Panduan Scraping Bali - Essential Guide

## 📋 Setup Cepat

### 1. Update Database
```bash
php artisan db:seed --class=BaliRegionSeeder
```

### 2. Verify Coverage
```bash
php verify_coverage.php
```

### 3. Visualisasi
```bash
open visualize_coverage.html
```

## 🧪 Test Scraping

### Test Zone Kecil
```bash
php artisan scrape:initial "Klungkung - Nusa Lembongan & Ceningan" cafe
```

### Test Zone Medium
```bash
php artisan scrape:initial "Badung - Canggu & Berawa" cafe
```

### Full Kabupaten
```bash
php artisan scrape:initial Badung cafe
```

## 📊 Coverage System

- **37 zones** covering all Bali
- **Adaptive grid** optimized for 60 result limit
- **Auto-subdivision** for dense areas
- **95-100% coverage** per kabupaten

## 💰 Cost Estimates

| Scope | Cost (1 kategori) |
|-------|------------------|
| 1 Zone | $2-5 |
| 1 Kabupaten | $8-18 |
| All Bali | $92-125 |

## ✅ Key Features

- ✅ **Multi-zone coverage** (no missed areas)
- ✅ **60 result limit handling** (auto-subdivision)
- ✅ **Adaptive grid sizing** (optimized per density)
- ✅ **Cost tracking** (real-time monitoring)
- ✅ **Duplicate prevention** (by place_id)

## 🔧 Troubleshooting

### API Rate Limit
- System handles automatically (10 req/sec)
- Wait and retry if needed

### Empty Results
- Check API key in `.env`
- Verify area has businesses on Google Maps

### High Cost
- Scrape per kabupaten, not all at once
- Monitor API usage in dashboard

## 📈 Monitoring

- Check logs: `tail -f storage/logs/laravel.log`
- Dashboard: `/dashboard`
- API usage: Google Cloud Console

---

**Ready to scrape! 🎯**
